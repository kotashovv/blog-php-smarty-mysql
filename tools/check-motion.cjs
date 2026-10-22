const { chromium, firefox } = require("playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:8080";
const out = "var/motion";
const pages = [
  ["home", "/"],
  ["category", "/category/architecture"],
  ["post", "/post/sustainable-systems"],
];
const results = [];
const browsers = [];

async function ready(page) {
  await page.evaluate(async () => {
    await document.fonts.ready;
    document.querySelectorAll("img").forEach((img) => (img.loading = "eager"));
    await Promise.all(
      [...document.images].map((img) => img.decode().catch(() => {})),
    );
  });
}

(async () => {
  fs.mkdirSync(out, { recursive: true });
  const chrome = await chromium.launch({ channel: "chrome", headless: true });
  browsers.push(chrome);
  for (const width of [1440, 390]) {
    for (const [name, route] of pages) {
      const context = await chrome.newContext({
        viewport: { width, height: 900 },
        reducedMotion: "no-preference",
      });
      await context.addInitScript(() => {
        window.motionRecord = [];
        document.addEventListener("animationstart", (event) => {
          for (const animation of event.target.getAnimations()) {
            if (
              !(animation instanceof CSSAnimation) ||
              !(animation.timeline instanceof DocumentTimeline)
            )
              continue;
            if (window.motionRecord.includes(animation)) continue;
            animation.pause();
            animation.currentTime = 0;
            window.motionRecord.push(animation);
          }
        });
      });
      const page = await context.newPage();
      await page.goto(base + route, { waitUntil: "networkidle" });
      await ready(page);
      await page.waitForFunction(() => window.motionRecord.length > 0);
      for (const time of [0, 200, 520, 850]) {
        await page.evaluate(
          (time) =>
            window.motionRecord.forEach(
              (animation) => (animation.currentTime = time),
            ),
          time,
        );
        await page.screenshot({
          path: `${out}/${name}-${width}-${time}ms.png`,
        });
      }
      const state = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        // Screenshots restore caret styles as empty style attributes on inputs.
        scripts: document.querySelectorAll(
          'script, style, [style]:not([style=""])',
        ).length,
        travel: getComputedStyle(document.documentElement)
          .getPropertyValue("--travel")
          .trim(),
        maxLoadEnd: Math.max(
          ...window.motionRecord.map(
            (a) => a.effect.getTiming().delay + a.effect.getTiming().duration,
          ),
        ),
        keyframeProperties: [
          ...new Set(
            window.motionRecord.flatMap((a) =>
              a.effect.getKeyframes().flatMap((f) => Object.keys(f)),
            ),
          ),
        ],
      }));
      assert.equal(state.overflow, false);
      assert.equal(
        state.scripts,
        0,
        "Inline code or presentation in product DOM",
      );
      assert.ok(state.maxLoadEnd <= 900);
      assert.equal(state.travel, width === 390 ? "16px" : "32px");
      assert.ok(
        state.keyframeProperties.every((p) =>
          [
            "offset",
            "computedOffset",
            "easing",
            "composite",
            "opacity",
            "transform",
            "clipPath",
          ].includes(p),
        ),
      );
      results.push({ mode: "assembly", name, width, ...state });
      if (width === 1440) {
        const card = page.locator(".card").first();
        const polygon = await card.evaluate(
          (e) => getComputedStyle(e).clipPath,
        );
        await card.locator(".card__title a").hover();
        await page.waitForTimeout(250);
        const hover = await card.evaluate((e) => ({
          transform: getComputedStyle(e).transform,
          polygon: getComputedStyle(e).clipPath,
        }));
        assert.equal(hover.polygon, polygon);
        assert.notEqual(hover.transform, "none");
      } else {
        await page.evaluate(() => window.scrollTo(0, 0));
        const mainTop = await page
          .locator("main")
          .evaluate((e) => e.getBoundingClientRect().top);
        await page.locator(".header__menu").click();
        await page.waitForTimeout(50);
        await page.evaluate(() =>
          window.motionRecord.forEach(
            (animation) => (animation.currentTime = 850),
          ),
        );
        assert.equal(
          await page
            .locator("main")
            .evaluate((e) => e.getBoundingClientRect().top),
          mainTop,
          "Menu moved the page flow",
        );
        await page.screenshot({ path: `${out}/${name}-390-menu.png` });
      }
      await context.close();
    }
  }

  const reduced = await chrome.newContext({
    viewport: { width: 390, height: 900 },
    reducedMotion: "reduce",
  });
  for (const [name, route] of pages) {
    const page = await reduced.newPage();
    await page.goto(base + route, { waitUntil: "networkidle" });
    await ready(page);
    const state = await page.evaluate(() => ({
      animations: document.getAnimations().length,
      h1Opacity: getComputedStyle(document.querySelector("h1")).opacity,
      h1Clip: getComputedStyle(document.querySelector("h1")).clipPath,
      headerTransform: getComputedStyle(document.querySelector(".header"))
        .transform,
      overflow: document.documentElement.scrollWidth > innerWidth,
    }));
    assert.equal(state.animations, 0);
    assert.equal(state.h1Opacity, "1");
    assert.equal(state.h1Clip, "none");
    assert.equal(state.headerTransform, "none");
    assert.equal(state.overflow, false);
    await page.locator(".header__menu").click();
    assert.equal(await page.evaluate(() => document.getAnimations().length), 0);
    await page.screenshot({ path: `${out}/${name}-390-reduced.png` });
    results.push({ mode: "reduced", name, ...state });
    await page.close();
  }
  await reduced.close();

  // A real browser with the feature disabled, rather than overriding CSS.supports().
  const fallback = await firefox.launch({
    headless: true,
    firefoxUserPrefs: {
      "layout.css.scroll-driven-animations.enabled": false,
      "dom.viewTransitions.enabled": false,
    },
  });
  browsers.push(fallback);
  for (const width of [390, 1440]) {
    for (const [name, route] of pages) {
      const page = await fallback.newPage({ viewport: { width, height: 900 } });
      await page.goto(base + route, { waitUntil: "networkidle" });
      await ready(page);
      await page.waitForTimeout(900);
      const state = await page.evaluate(() => ({
        timelineSupport: CSS.supports("animation-timeline: view()"),
        hiddenSections: [...document.querySelectorAll(".home__section")].filter(
          (e) => getComputedStyle(e).opacity !== "1",
        ).length,
        overflow: document.documentElement.scrollWidth > innerWidth,
      }));
      assert.equal(
        state.timelineSupport,
        false,
        "Fallback browser still supports view timelines",
      );
      assert.equal(state.hiddenSections, 0);
      assert.equal(state.overflow, false);
      results.push({ mode: "fallback-firefox", name, width, ...state });
      await page.screenshot({ path: `${out}/${name}-${width}-fallback.png` });
      await page.close();
    }
  }

  const touch = await chrome.newContext({
    viewport: { width: 390, height: 900 },
    isMobile: true,
    hasTouch: true,
  });
  const touchPage = await touch.newPage();
  await touchPage.goto(base, { waitUntil: "networkidle" });
  await touchPage.waitForTimeout(900);
  const touchState = await touchPage.evaluate(() => ({
    hover: matchMedia("(hover: hover)").matches,
    transform: getComputedStyle(document.querySelector(".card")).transform,
  }));
  assert.equal(touchState.hover, false);
  assert.equal(touchState.transform, "none");
  const section = touchPage.locator(".home__section").last();
  await section.evaluate((e) => e.scrollIntoView({ block: "center" }));
  await touchPage.waitForTimeout(100);
  const scroll = await section.evaluate((e) => ({
    opacity: getComputedStyle(e).opacity,
    timeline: e.getAnimations().some((a) => a.timeline instanceof ViewTimeline),
  }));
  assert.equal(scroll.opacity, "1");
  assert.equal(scroll.timeline, true);
  results.push({ mode: "touch-and-scroll", ...touchState, ...scroll });
  await touch.close();

  const noScript = await chrome.newContext({
    viewport: { width: 390, height: 900 },
    javaScriptEnabled: false,
  });
  const noScriptPage = await noScript.newPage();
  await noScriptPage.goto(base, { waitUntil: "networkidle" });
  await noScriptPage.waitForTimeout(900);
  await noScriptPage.locator(".header__menu").click();
  await noScriptPage.waitForTimeout(700);
  assert.equal(await noScriptPage.locator(".header__panel:popover-open").count(), 1);
  await noScriptPage.screenshot({ path: `${out}/home-390-no-javascript.png` });
  results.push({ mode: "javascript-disabled", menuOpen: true });
  await noScript.close();

  const gallery = `<html><meta charset="utf-8"><style>body{font:14px sans-serif;background:#f0f2e2;margin:20px}section{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}img{width:100%;display:block}h2{margin-bottom:8px}</style><h1>Механическая сборка · 0 / 200 / 520 / 850 ms</h1>${[1440, 390].flatMap((width) => pages.map(([name]) => `<h2>${name} · ${width}px</h2><section>${[0, 200, 520, 850].map((time) => `<div><p>${time} ms</p><img src="${name}-${width}-${time}ms.png"></div>`).join("")}</section>`)).join("")}</html>`;
  fs.writeFileSync(`${out}/gallery.html`, gallery);
  const galleryPage = await chrome.newPage({
    viewport: { width: 1400, height: 1000 },
  });
  await galleryPage.goto(
    "file:///" + path.resolve(out, "gallery.html").replaceAll("\\", "/"),
  );
  await galleryPage.screenshot({
    path: `${out}/assembly-contact-sheet.png`,
    fullPage: true,
  });
  fs.writeFileSync(
    `${out}/checks.json`,
    JSON.stringify(results, null, 2) + "\n",
  );
  console.log(JSON.stringify(results, null, 2));
})()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(async () => {
    for (const browser of browsers) await browser.close();
  });

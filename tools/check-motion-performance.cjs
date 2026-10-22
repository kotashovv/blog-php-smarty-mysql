const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:8080";
let browser;
(async () => {
  browser = await chromium.launch({ channel: "chrome", headless: true });
  fs.mkdirSync("var/motion", { recursive: true });
  const results = [];
  for (const width of [390, 1440]) {
    const context = await browser.newContext({
      viewport: { width, height: 900 },
    });
    await context.addInitScript(() => {
      window.assemblyAnimations = [];
      window.motionCLS = 0;
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries())
          if (!entry.hadRecentInput) window.motionCLS += entry.value;
      }).observe({ type: "layout-shift", buffered: true });
      document.addEventListener("animationstart", (event) => {
        if (
          !event.target.matches(
            ".header, .hero-photo, .hero-panel, .hero-directory, h1",
          )
        )
          return;
        for (const animation of event.target.getAnimations()) {
          if (
            animation.timeline instanceof DocumentTimeline &&
            !window.assemblyAnimations.includes(animation)
          ) {
            window.assemblyAnimations.push(animation);
          }
        }
      });
    });
    const page = await context.newPage();
    await page.goto(base, { waitUntil: "networkidle" });
    await page.evaluate(async () => {
      await document.fonts.ready;
      await Promise.all(
        [...document.images]
          .filter((i) => i.loading !== "lazy")
          .map((i) => i.decode().catch(() => {})),
      );
    });
    await page.waitForTimeout(950);
    const session = await context.newCDPSession(page);
    await session.send("Performance.enable");
    await session.send("Tracing.start", {
      categories: "devtools.timeline,blink.animations,cc",
      transferMode: "ReturnAsStream",
    });
    await page.evaluate(() =>
      window.assemblyAnimations.forEach((a) => {
        a.currentTime = 0;
        a.play();
      }),
    );
    await page.waitForTimeout(80);
    const before = Object.fromEntries(
      (await session.send("Performance.getMetrics")).metrics.map((m) => [
        m.name,
        m.value,
      ]),
    );
    await page.waitForTimeout(120);
    const after = Object.fromEntries(
      (await session.send("Performance.getMetrics")).metrics.map((m) => [
        m.name,
        m.value,
      ]),
    );
    const complete = new Promise((resolve) =>
      session.once("Tracing.tracingComplete", resolve),
    );
    await session.send("Tracing.end");
    const { stream } = await complete;
    let trace = "";
    for (;;) {
      const part = await session.send("IO.read", {
        handle: stream,
        size: 1024 * 1024,
      });
      trace += part.base64Encoded
        ? Buffer.from(part.data, "base64").toString()
        : part.data;
      if (part.eof) break;
    }
    await session.send("IO.close", { handle: stream });
    fs.writeFileSync(`var/motion/performance-${width}.trace.json`, trace);
    const state = {
      width,
      sampleWindow:
        "80-200ms of a warm assembly replay; start/end setup excluded",
      measuredDurationMs: (after.Timestamp - before.Timestamp) * 1000,
      layoutCount: after.LayoutCount - before.LayoutCount,
      recalcStyleCount: after.RecalcStyleCount - before.RecalcStyleCount,
      sampleTraceEvents: JSON.parse(trace)
        .traceEvents.filter(
          (e) =>
            ["Layout", "UpdateLayoutTree"].includes(e.name) &&
            e.ts >= before.Timestamp * 1e6 &&
            e.ts <= after.Timestamp * 1e6,
        )
        .map((e) => ({
          name: e.name,
          durationUs: e.dur,
          elements: e.args?.elementCount,
        })),
      fullReplayStyleEvents: JSON.parse(trace).traceEvents.filter(
        (e) => e.name === "UpdateLayoutTree",
      ).length,
      cls: await page.evaluate(() => window.motionCLS),
    };
    results.push(state);
    assert.equal(state.layoutCount, 0, "Layout during movement");
    // Record style servicing honestly: clip masks / view timelines can require it.
    // Only layout and CLS are invariant assertions across browser versions.
    assert.equal(state.cls, 0, "Unexpected layout shift");
    await page.waitForTimeout(650);
    await Promise.all([
      page.waitForURL("**/post/sustainable-systems"),
      page.locator(".hero-panel .button").click(),
    ]);
    await page.waitForTimeout(900);
    assert.equal(await page.locator("h1").count(), 1);
    assert.equal(
      await page
        .locator(".header")
        .evaluate((e) => getComputedStyle(e).opacity),
      "1",
    );
    await page.screenshot({ path: `var/motion/navigation-${width}.png` });
    await context.close();
  }
  fs.writeFileSync(
    "var/motion/performance-checks.json",
    JSON.stringify(results, null, 2) + "\n",
  );
  console.log(JSON.stringify(results, null, 2));
})()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(async () => {
    await browser?.close();
  });

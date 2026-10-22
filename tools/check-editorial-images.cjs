const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:8080";
let browser;
(async () => {
  fs.mkdirSync("var/editorial-preview", { recursive: true });
  browser = await chromium.launch({ channel: "chrome", headless: true });
  const results = [];
  for (const width of [390, 1440]) {
    for (const [name, route] of [
      ["home", "/"],
      ["category", "/category/architecture"],
      ["post", "/post/sustainable-systems"],
    ]) {
      const page = await browser.newPage({
        viewport: { width, height: 1000 },
        reducedMotion: "reduce",
      });
      const errors = [];
      page.on("pageerror", (error) => errors.push(error.message));
      const response = await page.goto(base + route, {
        waitUntil: "networkidle",
      });
      assert.equal(response.status(), 200);
      await page.evaluate(async () => {
        await document.fonts.ready;
        document.querySelectorAll("img").forEach((img) => {
          img.loading = "eager";
        });
        await Promise.all([...document.images].map((img) => img.decode()));
      });
      const state = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        images: [...document.images].map((img) => ({
          src: img.getAttribute("src"),
          width: img.naturalWidth,
          height: img.naturalHeight,
        })),
        heroFilter: document.querySelector(".hero-photo img")
          ? getComputedStyle(document.querySelector(".hero-photo img")).filter
          : null,
      }));
      assert.equal(state.overflow, false);
      assert.ok(state.images.length > 0);
      for (const image of state.images) {
        assert.match(image.src, /^\/assets\/images\/editorial\/.*\.webp$/);
        assert.equal(image.width, 1200);
        assert.equal(image.height, 800);
      }
      if (name === "home") assert.equal(state.heroFilter, "none");
      assert.deepEqual(errors, []);
      await page.screenshot({
        path: `var/editorial-preview/${name}-${width}.png`,
        fullPage: name === "category",
      });
      results.push({ name, width, ...state, errors });
      await page.close();
    }
  }
  fs.writeFileSync(
    "var/editorial-preview/checks.json",
    JSON.stringify(results, null, 2) + "\n",
  );
  console.log(
    "Editorial WebP images: 6 pages/widths, correct dimensions, no missing images or overflow, color hero: OK",
  );
})()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(async () => {
    await browser?.close();
  });

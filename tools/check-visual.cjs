const { chromium } = require("playwright");
const fs = require("node:fs");
const baseUrl = process.env.PREVIEW_URL || "http://127.0.0.1:8080";
let browser;
(async () => {
  fs.mkdirSync("tools/visual-output", { recursive: true });
  browser = await chromium.launch({ channel: "chrome", headless: true });
  const results = [];
  for (const width of [390, 1440]) {
    for (const [name, route] of [
      ["home", "/"],
      ["category", "/category/architecture"],
      ["post", "/post/sustainable-systems"],
      ["404", "/missing"],
    ]) {
      const page = await browser.newPage({
        viewport: { width, height: 1000 },
        deviceScaleFactor: 1,
      });
      const errors = [];
      page.on("pageerror", (e) => errors.push(e.message));
      const response = await page.goto(`${baseUrl}${route}`, {
        waitUntil: "networkidle",
      });
      const expectedStatus = name === "404" ? 404 : 200;
      if (response.status() !== expectedStatus)
        throw Error(
          `${route}: HTTP ${response.status()}, expected ${expectedStatus}`,
        );
      await page.evaluate(() => document.fonts.ready);
      // Load lazy images before the full-page capture.
      await page
        .locator("img")
        .evaluateAll((images) =>
          images.forEach((img) => (img.loading = "eager")),
        );
      await page.evaluate(() =>
        Promise.all(
          [...document.images].map((img) => img.decode().catch(() => {})),
        ),
      );
      const state = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        brokenImages: [...document.images]
          .filter((i) => !i.naturalWidth)
          .map((i) => i.src),
        heading: document.querySelector("h1").innerText,
        fonts: [...document.fonts]
          .filter((f) => f.status === "error")
          .map((f) => f.family),
      }));
      await page.screenshot({
        path: `tools/visual-output/${name}-${width}.png`,
        fullPage: true,
      });
      if (width === 390 && name !== "404") {
        await page.locator("summary").click();
        if (!(await page.locator(".header__mobile nav").isVisible()))
          throw Error("Mobile navigation is inaccessible");
      }
      results.push({
        name,
        width,
        status: response.status(),
        ...state,
        errors,
      });
      await page.close();
    }
  }
  await browser.close();
  fs.writeFileSync(
    "tools/visual-output/results.json",
    JSON.stringify(results, null, 2),
  );
  console.log(JSON.stringify(results, null, 2));
  if (
    results.some(
      (r) =>
        r.overflow ||
        r.brokenImages.length ||
        r.fonts.length ||
        r.errors.length,
    )
  )
    process.exitCode = 1;
})().catch(async (e) => {
  console.error(e);
  await browser?.close();
  process.exitCode = 1;
});

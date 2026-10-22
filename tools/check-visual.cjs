const { chromium } = require("playwright");
const fs = require("node:fs");
(async () => {
  fs.mkdirSync("tools/visual-output", { recursive: true });
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  const results = [];
  for (const width of [390, 1440]) {
    for (const [name, route] of [
      ["home", "/"],
      ["category", "/category"],
      ["post", "/post"],
      ["404", "/missing"],
    ]) {
      const page = await browser.newPage({
        viewport: { width, height: 1000 },
        deviceScaleFactor: 1,
      });
      const errors = [];
      page.on("pageerror", (e) => errors.push(e.message));
      const response = await page.goto(`http://127.0.0.1:8088${route}`, {
        waitUntil: "networkidle",
      });
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
  if (results.some((r) => r.overflow || r.fonts.length || r.errors.length))
    process.exitCode = 1;
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});

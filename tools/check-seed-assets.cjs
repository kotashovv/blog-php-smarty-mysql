const assert = require("node:assert/strict");
const fs = require("node:fs");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:8080";
(async () => {
  const tokens = JSON.parse(fs.readFileSync("design/tokens.json", "utf8"));
  const scss = fs
    .readFileSync("assets/scss/tokens/_colors.scss", "utf8")
    .toLowerCase();
  for (const [name, color] of Object.entries(tokens.colors.tones)) {
    if (name === "white") assert.match(scss, /"white":\s*#fff(?:fff)?\b/);
    else if (name === "ink") assert.ok(scss.includes("$ink: " + color));
    else
      assert.ok(scss.includes(`"${name}": ${color}`), `${name}: palette drift`);
  }
  const results = [];
  for (let number = 1; number <= 40; number++) {
    const url = `/uploads/seed/post-${String(number).padStart(4, "0")}.svg`;
    const response = await fetch(base + url);
    assert.equal(response.status, 200, url);
    assert.match(response.headers.get("content-type"), /image\/svg\+xml/);
    const svg = await response.text();
    assert.match(svg, /<svg /);
    assert.doesNotMatch(
      svg,
      /<script|<image|<foreignObject|\shref=|\son[a-z]+=/i,
    );
    results.push({ url, status: response.status });
  }
  const obsolete = await fetch(base + "/uploads/seed/post-0041.svg");
  assert.equal(obsolete.status, 404, "Old SVG remains after default --fresh");
  fs.mkdirSync("var", { recursive: true });
  fs.writeFileSync(
    "var/stage2-assets.json",
    JSON.stringify(results, null, 2) + "\n",
  );
  console.log(
    "Seed assets: 40 local SVGs served by nginx; obsolete SVG absent; JSON/SCSS palettes match.",
  );
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

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
  const files = fs.readdirSync("public/assets/images/editorial");
  assert.equal(files.length, 10);
  for (const file of files) {
    assert.match(file, /\.webp$/);
    const url = `/assets/images/editorial/${file}`;
    const response = await fetch(base + url);
    assert.equal(response.status, 200, url);
    assert.match(response.headers.get("content-type"), /image\/webp/);
    const bytes = Buffer.from(await response.arrayBuffer());
    assert.equal(bytes.toString("ascii", 0, 4), "RIFF");
    assert.equal(bytes.toString("ascii", 8, 12), "WEBP");
    assert.ok(
      bytes.equals(fs.readFileSync(`public/assets/images/editorial/${file}`)),
    );
    results.push({ url, status: response.status, bytes: bytes.length });
  }
  fs.mkdirSync("var", { recursive: true });
  fs.writeFileSync(
    "var/stage2-assets.json",
    JSON.stringify(results, null, 2) + "\n",
  );
  console.log(
    "Seed assets: 10 reusable local WebPs served by nginx with matching bytes and MIME; JSON/SCSS palettes match.",
  );
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

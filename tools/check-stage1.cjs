const assert = require("node:assert/strict");
const fs = require("node:fs");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:8080";
(async () => {
  const results = [];
  for (const [url, expected] of [
    ["/", 200],
    ["/category/architecture", 200],
    ["/post/sustainable-systems", 200],
    ["/missing", 404],
    ["/category/missing", 404],
    ["/post/missing", 404],
    ["/category/%27%20OR%20%271%27=%271", 404],
    ["/.env", 404],
    ["/.git/config", 404],
    ["/vendor/autoload.php", 404],
    ["/vendor/", 404],
    ["/src/", 404],
    ["/config/", 404],
    ["/var/", 404],
    ["/tools/fixtures/views.json", 404],
    ["/uploads/test.php", 404],
    ["/uploads/test.PHP/path", 404],
    ["/assets/css/main.css", 200],
    ["/assets/fonts/unbounded-cyrillic.woff2", 200],
  ]) {
    const response = await fetch(base + url);
    const body = await response.text();
    assert.equal(response.status, expected, url);
    for (const header of [
      "x-content-type-options",
      "x-frame-options",
      "referrer-policy",
      "content-security-policy",
    ]) {
      assert.ok(response.headers.get(header), `${url}: missing ${header}`);
    }
    assert.equal(
      response.headers.get("server"),
      "nginx",
      "Server version leaked",
    );
    assert.equal(
      response.headers.get("x-powered-by"),
      null,
      "PHP version leaked",
    );
    assert.ok(
      !/Stack trace|Fatal error|\/var\/www\/html/.test(body),
      `${url}: details leaked`,
    );
    if (url === "/missing") assert.match(body, /МАТЕРИАЛ НЕ СУЩЕСТВУЕТ/);
    results.push({ url, status: response.status });
  }
  const post = await fetch(base + "/", { method: "POST" });
  assert.equal(post.status, 405);
  assert.equal(post.headers.get("allow"), "GET, HEAD");
  results.push({ url: "POST /", status: post.status });
  fs.mkdirSync("var", { recursive: true });
  fs.writeFileSync(
    "var/stage1-http.json",
    JSON.stringify(results, null, 2) + "\n",
  );
  console.log(
    `HTTP: ${results.length} checks OK; security headers present; nginx/PHP versions hidden.`,
  );
  console.log(JSON.stringify(results, null, 2));
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

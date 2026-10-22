const assert = require("node:assert/strict");
const fs = require("node:fs");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:8080";
(async () => {
  const results = [];
  for (const [url, status] of [
    ["/", 200],
    ["/category/architecture", 200],
    ["/post/sustainable-systems", 200],
    ["/missing", 404],
  ]) {
    const get = await fetch(base + url);
    assert.equal(get.status, status);
    const head = await fetch(base + url, { method: "HEAD" });
    assert.equal(head.status, status);
    assert.equal(await head.text(), "", `${url}: HEAD body is not empty`);
    for (const header of [
      "content-type",
      "x-content-type-options",
      "x-frame-options",
      "referrer-policy",
      "content-security-policy",
      "server",
    ]) {
      assert.ok(head.headers.get(header), `${url}: missing ${header}`);
      assert.equal(
        head.headers.get(header),
        get.headers.get(header),
        `${url}: changed ${header}`,
      );
    }
    assert.equal(head.headers.get("x-powered-by"), null);
    assert.equal(head.headers.get("server"), "nginx");
    results.push({ url, status, bodyBytes: 0, securityHeaders: "OK" });
    await get.arrayBuffer();
  }
  const upload = await fetch(base + "/uploads/stage1-review.txt");
  assert.equal(upload.status, 200);
  assert.equal(await upload.text(), "stage1-review-created-by-www-data\n");
  fs.mkdirSync("var", { recursive: true });
  fs.writeFileSync(
    "var/stage1-review-http.json",
    JSON.stringify(results, null, 2) + "\n",
  );
  console.log(
    "HEAD: 3 pages and 404 match GET headers, no body; www-data upload served by nginx: OK.",
  );
  console.log(JSON.stringify(results, null, 2));
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

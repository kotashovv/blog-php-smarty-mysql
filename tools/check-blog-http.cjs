const assert = require("node:assert/strict");
const fs = require("node:fs");
const { execFileSync } = require("node:child_process");
const { load } = require("cheerio");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:8080";
const expected = JSON.parse(
  fs.readFileSync("var/blog-checks.json", "utf8").replace(/^\uFEFF/, ""),
);
const results = [];

function postState(slug) {
  const code =
    'require "vendor/autoload.php"; $pdo=(new App\\Core\\Database(new App\\Core\\Config(getcwd())))->connection();' +
    '$p=(new App\\Repository\\PostRepository($pdo))->findBySlug($argv[1]); echo json_encode(["id"=>$p->id,"views"=>$p->views]);';
  return JSON.parse(
    execFileSync(
      "docker",
      ["compose", "exec", "-T", "app", "php", "-r", code, slug],
      { encoding: "utf8" },
    ),
  );
}

async function request(route, status = 200, options = {}) {
  const response = await fetch(base + route, {
    redirect: "manual",
    ...options,
  });
  const html = await response.text();
  assert.equal(response.status, status, route);
  assert.ok(
    !/Stack trace|Fatal error|Warning:|\/var\/www\/html/.test(html),
    route,
  );
  assert.ok(
    !response.headers
      .get("content-security-policy")
      .includes("googleusercontent"),
  );
  results.push({ route, method: options.method || "GET", status });
  return { response, html, $: load(html) };
}

function ids($, selector) {
  return $(selector)
    .map((_, e) => Number($(e).attr("data-post-id")))
    .get();
}

(async () => {
  const home = await request("/");
  assert.equal(home.$(".home__section").length, expected.home.length);
  for (const section of expected.home) {
    const selector = `.home__section[data-category-id="${section.id}"]`;
    assert.deepEqual(ids(home.$, `${selector} .card`), section.posts);
    assert.ok(home.$(`${selector} .card`).first().hasClass("card--large"));
  }
  const routes = new Set(
    home
      .$("a[href]")
      .map((_, e) => home.$(e).attr("href"))
      .get()
      .filter((x) => x.startsWith("/")),
  );
  for (const route of routes) await request(route, 200, { method: "HEAD" });

  for (const sort of ["date", "views"]) {
    for (const page of [1, 2, 3]) {
      const { $ } = await request(
        `/category/architecture?sort=${sort}&page=${page}`,
      );
      assert.deepEqual(
        ids($, ".category__posts .card"),
        expected.category[sort][page],
      );
      assert.ok($(".pagination a").length > 0);
      $(".pagination a").each((_, e) =>
        assert.match($(e).attr("href"), new RegExp(`sort=${sort}$`)),
      );
    }
  }
  for (const query of [
    "page=0",
    "page=-1",
    "page=abc",
    "page[]=9",
    "page=-9999999999999999999999",
    "sort=views%3BDROP%20TABLE%20posts",
    "sort=",
    "sort[]=views",
  ]) {
    const { $ } = await request("/category/architecture?" + query);
    assert.deepEqual(
      ids($, ".category__posts .card"),
      expected.category.date[1],
      query,
    );
    assert.equal($(".sort__link[aria-current]").text(), "По дате");
  }
  for (const route of [
    "/category/architecture?page=9999",
    "/category/architecture?page=9999999999999999999999",
    "/category/missing",
    "/post/missing",
    "/category/%27%20OR%20%271%27=%271",
    "/category/UPPER",
    "/category",
    "/post",
    "/category/engineering?page=2",
    "/category/editorial-notes?page=2",
  ]) {
    await request(route, 404);
  }
  const empty = await request("/category/editorial-notes");
  assert.equal(empty.$(".category__posts .card").length, 0);
  assert.match(empty.$(".category__posts").text(), /пока нет статей/);
  const isolated = await request("/category/engineering");
  assert.equal(isolated.$(".category__posts .card").length, 1);

  const slug = "sustainable-systems";
  const before = postState(slug);
  const head = await request("/post/" + slug, 200, { method: "HEAD" });
  assert.equal(head.html, "");
  assert.equal(head.response.headers.get("set-cookie"), null);
  assert.equal(postState(slug).views, before.views);
  const first = await request("/post/" + slug);
  const setCookie = first.response.headers.get("set-cookie");
  assert.match(setCookie, /HttpOnly/i);
  assert.match(setCookie, /SameSite=Lax/i);
  const cookie = setCookie.split(";")[0];
  assert.equal(postState(slug).views, before.views + 1);
  assert.equal(
    first.$(".post__views").text().replace(/\D/g, ""),
    String(before.views + 1),
  );
  assert.deepEqual(ids(first.$, ".post-related .card"), expected.related[slug]);
  await request("/post/" + slug, 200, { headers: { cookie } });
  assert.equal(postState(slug).views, before.views + 1);
  await request("/post/" + slug);
  assert.equal(postState(slug).views, before.views + 2);

  // Multiple first visits to another article share an existing session and its lock.
  const parallelSlug = "modular-monolith";
  const parallelBefore = postState(parallelSlug);
  await Promise.all(
    Array.from({ length: 6 }, () =>
      request("/post/" + parallelSlug, 200, { headers: { cookie } }),
    ),
  );
  assert.equal(postState(parallelSlug).views, parallelBefore.views + 1);
  const distinctBefore = postState(slug);
  await Promise.all(Array.from({ length: 6 }, () => request("/post/" + slug)));
  assert.equal(postState(slug).views, distinctBefore.views + 6);
  const related = await request("/post/" + expected.isolated_slug);
  assert.deepEqual(
    ids(related.$, ".post-related .card"),
    expected.related[expected.isolated_slug],
  );

  for (const page of [home, first, related]) {
    assert.equal(
      page.$(
        "script, style, [style], form, figcaption, .post__actions, .newsletter, .header__subscribe",
      ).length,
      0,
    );
    assert.equal(page.$("h1").length, 1);
    page
      .$("img")
      .each((_, e) =>
        assert.match(page.$(e).attr("src"), /^\/uploads\/seed\//),
      );
  }
  const evidence = {
    result: "OK",
    requests: results,
    counter: {
      before: before.views,
      headIncrement: 0,
      firstIncrement: 1,
      sameSessionIncrement: 0,
      newSessionIncrement: 1,
      parallelFirstRequests: 6,
      parallelIncrement: 1,
      parallelDistinctSessions: 6,
      distinctSessionIncrement: 6,
    },
    realLinksChecked: routes.size,
  };
  fs.writeFileSync(
    "var/blog-http.json",
    JSON.stringify(evidence, null, 2) + "\n",
  );
  console.log(JSON.stringify(evidence, null, 2));
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

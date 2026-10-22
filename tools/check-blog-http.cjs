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
    for (const dir of ["desc", "asc"]) {
      for (const page of [1, 2, 3]) {
        const { $ } = await request(
          `/category/architecture?sort=${sort}&dir=${dir}&page=${page}`,
        );
        assert.deepEqual(
          ids($, ".category__posts .card"),
          expected.category[sort][dir][page],
        );
        assert.ok($(".pagination a").length > 0);
        $(".pagination a").each((_, e) =>
          assert.match(
            $(e).attr("href"),
            new RegExp(`sort=${sort}&dir=${dir}$`),
          ),
        );
        // The active sort link offers the opposite direction on the next click.
        assert.match(
          $(".sort__link[aria-current]").attr("href"),
          new RegExp(`sort=${sort}&dir=${dir === "desc" ? "asc" : "desc"}$`),
        );
        assert.equal(
          $(".sort__link[aria-current] .sort__arrow").text(),
          dir === "desc" ? "↓" : "↑",
        );
      }
    }
  }
  // Header: "Главная" + 3 categories + one "Остальное" dropdown, on every kind of page (not only home).
  const moreCount = expected.home.length - 3;
  for (const route of ["/", "/category/architecture", "/category/culture", "/post/modular-monolith"]) {
    const { $ } = await request(route);
    assert.equal($(".header__nav > .header__link").length, 4, route);
    assert.equal($(".header__more-menu .header__link").length, moreCount, route);
    assert.equal($(".header__panel .header__link").length, 4 + moreCount, route);
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
      expected.category.date.desc[1],
      query,
    );
    assert.equal($(".sort__link[aria-current]").text().trim(), "По дате ↓");
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
  // Demo rule: every GET is a view, even with the same session (a refresh counts).
  await request("/post/" + slug, 200, { headers: { cookie } });
  assert.equal(postState(slug).views, before.views + 2);
  await request("/post/" + slug);
  assert.equal(postState(slug).views, before.views + 3);

  // Parallel refreshes of another article are all counted.
  const parallelSlug = "modular-monolith";
  const parallelBefore = postState(parallelSlug);
  await Promise.all(
    Array.from({ length: 6 }, () =>
      request("/post/" + parallelSlug, 200, { headers: { cookie } }),
    ),
  );
  assert.equal(postState(parallelSlug).views, parallelBefore.views + 6);
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
        "script, style, [style], figcaption, .post__actions",
      ).length,
      0,
    );
    assert.equal(page.$(".header__subscribe").length, 1);
    assert.equal(page.$("form").length, page.$('form[action="/subscribe"]').length);
    assert.equal(page.$("h1").length, 1);
    page
      .$("img")
      .each((_, e) =>
        assert.match(
          page.$(e).attr("src"),
          /^\/assets\/images\/editorial\/.*\.webp$/,
        ),
      );
  }
  const evidence = {
    result: "OK",
    requests: results,
    counter: {
      before: before.views,
      headIncrement: 0,
      firstIncrement: 1,
      sameSessionIncrement: 1,
      newSessionIncrement: 1,
      parallelFirstRequests: 6,
      parallelIncrement: 6,
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

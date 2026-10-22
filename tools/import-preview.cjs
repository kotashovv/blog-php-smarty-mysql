// Rebuild development fixtures from the supplied static layouts. Never used by controllers.
const fs = require("node:fs");
const path = require("node:path");
const { load } = require("cheerio");
const read = (dir) =>
  load(fs.readFileSync(path.join(dir, "code.html"), "utf8"));
const text = ($el) => $el.text().replace(/\s+/g, " ").trim();
const image = ($el) =>
  $el.length
    ? {
        src: $el.attr("src"),
        alt: $el.attr("alt") || $el.attr("data-alt") || "",
        width: 1200,
        height: 800,
      }
    : null;
const home = read("bento_editorial_2");
const category = read("bento_editorial_3");
const article = read("bento_editorial_1");
const error = read("404_bento_editorial");
const nav = home("header nav a")
  .toArray()
  .map((el, i) => ({
    label: text(home(el)),
    url: i ? "/category" : "/",
    active: i === 0,
  }));
const chips = nav
  .slice(1)
  .map(({ label, url }) => ({ label, url, active: false }));
function card($, el, index) {
  const tile = $(el);
  const heading = tile.find("h3,h2").first();
  const title = heading.length ? text(heading) : text(tile.find("a").first());
  const label =
    text(
      tile
        .find("div")
        .filter(
          (_, e) =>
            $(e).children().length === 0 &&
            ($(e).attr("class") || "").includes("inline-flex"),
        )
        .first(),
    ) ||
    text(tile.find("span").first()) ||
    text(tile.find("p").first());
  const content = text(tile);
  const date =
    content.match(
      /\d{1,2}\s+(?:ЯНВАРЯ|ФЕВРАЛЯ|МАРТА|АПРЕЛЯ|МАЯ|ИЮНЯ|ИЮЛЯ|АВГУСТА|СЕНТЯБРЯ|ОКТЯБРЯ|НОЯБРЯ|ДЕКАБРЯ)\s+\d{4}/,
    )?.[0] || "";
  const months = [
    "ЯНВАРЯ",
    "ФЕВРАЛЯ",
    "МАРТА",
    "АПРЕЛЯ",
    "МАЯ",
    "ИЮНЯ",
    "ИЮЛЯ",
    "АВГУСТА",
    "СЕНТЯБРЯ",
    "ОКТЯБРЯ",
    "НОЯБРЯ",
    "ДЕКАБРЯ",
  ];
  const [day, month, year] = date.split(" ");
  const viewsLabel = content.match(/[\d.]+K?\s+ПРОСМОТРОВ/)?.[0] || "";
  const views =
    parseFloat(viewsLabel) * (viewsLabel.includes("K") ? 1000 : 1) || 0;
  return {
    title,
    url: "/post",
    label,
    stat: tile.find("h3").length ? "" : text(tile.find("span").eq(1)) || date,
    excerpt: text(tile.find("p").first()),
    image: image(tile.find("img").first()),
    category: label.split("/")[0].trim(),
    datetime: date
      ? `${year}-${String(months.indexOf(month) + 1).padStart(2, "0")}-${day.padStart(2, "0")}`
      : "",
    date,
    views,
    viewsLabel,
  };
}
const categoryCards = category("article")
  .toArray()
  .map((el, i) => card(category, el, i));
const sections = home("main section").toArray();
const homeSections = sections.slice(1, 4).map((el, i) => {
  const section = home(el);
  const cards = section.find("article").toArray();
  const anchors = section
    .find("a")
    .filter((_, a) => home(a).find("h3").length)
    .toArray();
  const posts = (cards.length ? cards : anchors)
    .slice(0, 3)
    .map((el, j) => card(home, el, j));
  posts.sort((a, b) => Number(!!b.image) - Number(!!a.image));
  return {
    eyebrow: `0${i + 1} / РАЗДЕЛ`,
    title: text(section.find("h2").first()),
    url: "/category",
    posts,
  };
});
const hero = home(sections[0]);
const newsletter = home(sections.at(-1));
const body = article("main article").clone();
body.children().last().remove();
body
  .find("div")
  .filter((_, el) =>
    (article(el).attr("class") || "").includes("bg-tertiary-fixed"),
  )
  .addClass("article-callout");
body
  .find("div")
  .filter((_, el) =>
    (article(el).attr("class") || "").includes("bg-surface-container-lowest"),
  )
  .addClass("article-principle");
// Preserve semantic prose only; generated utility classes and inline presentation are discarded.
body.find("div").each((_, el) => {
  const e = article(el);
  if (e.find("h2").length) e.find("div").remove();
});
body.find("*").each((_, el) => {
  const allowed = (el.attribs.class || "")
    .split(" ")
    .filter((c) => ["article-callout", "article-principle"].includes(c));
  for (const attr of Object.keys(el.attribs || {}))
    if (!["href", "src", "alt"].includes(attr)) delete el.attribs[attr];
  if (allowed.length) el.attribs.class = allowed.join(" ");
});
body
  .find("div")
  .toArray()
  .reverse()
  .forEach((el) => {
    const e = article(el);
    if (!e.attr("class")) e.replaceWith(e.contents());
  });
const errorLinks = error("main nav a")
  .toArray()
  .map((el, i) => ({
    label: text(error(el).find("span").last()),
    url: "/category",
    index: `0${i + 1}`,
  }));
const data = {
  common: {
    site: {
      language: "ru",
      name: text(home("header a").first()),
      homeUrl: "/",
      subscribeUrl: "/#newsletter",
      copyright: text(home("footer p").first()),
    },
    navigation: nav,
    footerLinks: home("footer nav a")
      .toArray()
      .map((el) => ({ label: text(home(el)), url: "/" })),
    ui: {
      skipContent: "Перейти к содержимому",
      mainNavigation: "Разделы издания",
      footerNavigation: "О проекте",
      subscribe: "Подписаться",
      menu: "Меню /",
      breadcrumbs: "Путь к странице",
      readArticle: "Читать статью",
      sortLabel: "Сортировка материалов",
      pagination: "Страницы материалов",
      previous: "← Назад",
      next: "Вперёд →",
      page: "Страница",
      noPosts: "Материалов пока нет",
      categories: "Рубрики:",
      allPosts: "Все материалы",
      relatedPosts: "Похожие материалы",
    },
    sortOptions: [
      { value: "date", label: "По дате" },
      { value: "views", label: "По просмотрам" },
    ],
    csrfToken: "preview-only-not-a-production-token",
  },
  home: {
    meta: {
      title: "Блог / Bento Editorial",
      description: text(hero.find("p").eq(1)),
    },
    hero: {
      image: image(hero.find("img").first()),
      issue: text(hero.find("span").first()),
      imageLabel: "Editorial",
      captionLabel: text(hero.find("p").first()),
      caption: text(hero.find("h2").first()),
      eyebrow: "MANIFESTO / VOL. 2025",
      title: text(hero.find("h1")),
      description: text(hero.find("p").eq(1)),
      actionUrl: "/post",
      actionLabel: "Читать свежий выпуск",
      updatedLabel: "// Обновлено сегодня",
      summary: "124 лонгрида / 100% открытый доступ",
      archive: "Архив 2021—2025",
    },
    categoryChips: chips,
    categories: homeSections,
    newsletter: {
      label: "Письма редакции",
      title: text(newsletter.find("h2")),
      description: text(newsletter.find("p").first()),
      action: "/preview-subscription",
      emailLabel: "Электронная почта",
      placeholder: "YOUR@EMAIL.COM",
      submitLabel: "Подписаться",
      consent:
        "Демонстрационная форма. Подключите обработчик подписки в приложении.",
    },
  },
  category: {
    meta: {
      title: "Архитектура / Блог",
      description: text(category("main header p").first()),
    },
    breadcrumbs: [{ label: "Главная", url: "/" }, { label: "Архитектура" }],
    category: {
      label: "Рубрика издания",
      code: "[ / ARCH-VOL.04 | SPEC-2025 ]",
      title: text(category("h1")),
      description: text(category("main header p").first()),
      topics: [
        "Фокус: распределённые системы",
        "Паттерны интеграции",
        "Масштабируемость",
      ],
      totalLabel: "14 материалов",
    },
    posts: categoryCards,
    page: 2,
    pages: 5,
    sort: "date",
    baseUrl: "/category",
  },
  post: {
    meta: {
      title: text(article("h1")),
      description: text(article("h1").next("p")),
    },
    breadcrumbs: [
      { label: "Главная", url: "/" },
      { label: "Архитектура", url: "/category" },
      { label: text(article("h1")) },
    ],
    post: {
      title: text(article("h1")),
      category: "Архитектура",
      label: "// Специальный репортаж",
      excerpt: text(article("h1").next("p")),
      author: "Константин Минин",
      authorRole: "Ведущий архитектор",
      datetime: "2025-05-24",
      date: "24 мая 2025",
      readingLabel: "8 мин чтения",
      viewsLabel: "3 840 просмотров",
      image: image(article("main img").first()),
      caption:
        "Монолитная конструкция института прикладной физики / Архитектурная ясность как метафора системы",
      bodyHtml: body.html(),
      tags: chips.slice(0, 2),
      actions: [],
      relatedLabel: "Том 04 / Архив",
    },
    relatedPosts: categoryCards.slice(1, 4),
  },
  404: {
    meta: {
      title: "404 / Материал не найден",
      description: text(error("main section p")),
    },
    error: {
      systemLabel: "SYS.ERR // 0x404_NULL_REFERENCE",
      archiveLabel: "Архив // 2025",
      label: "Ошибка 404 / Страница не найдена",
      codeLabel: "[ КОД / 404 ]",
      code: "404",
      title: text(error("h2")),
      description: text(error("main section p")),
      homeLabel: "Вернуться на главную",
      statusLabel: "Статус: оборванный путь",
      navigationLabel: "Навигация по разделам / Актуальное",
      categoriesLabel: "4 категории",
      categories: errorLinks,
      footerLabel: "Блог / Независимое издание о форме и смысле",
      footerCode: "INDEX / ARCHIVE / RETRY",
    },
  },
};
fs.mkdirSync("tools/fixtures", { recursive: true });
fs.writeFileSync(
  "tools/fixtures/views.json",
  JSON.stringify(data, null, 2) + "\n",
);
console.log(
  "Imported fixture cards:",
  homeSections.map((s) => s.posts.length),
  categoryCards.length,
);

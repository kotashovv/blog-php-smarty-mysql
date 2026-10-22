// Progressive enhancement: sorting and pagination swap the listing in place.
// Without this script the links are ordinary page loads.
(() => {
  const SELECTOR = "[data-listing]";
  let controller = null;
  let swapTimer = 0;

  const region = () => document.querySelector(SELECTOR);

  const load = async (url, mode, focusSelector) => {
    const current = region();
    if (!current) {
      location.assign(url);
      return;
    }
    controller?.abort();
    controller = new AbortController();
    current.classList.add("listing--loading");
    current.setAttribute("aria-busy", "true");
    try {
      const response = await fetch(url, {
        signal: controller.signal,
        headers: { "X-Requested-With": "fetch" },
        credentials: "same-origin",
      });
      const type = response.headers.get("Content-Type") || "";
      if (!response.ok || !type.includes("text/html")) {
        throw new Error("unexpected response");
      }
      const doc = new DOMParser().parseFromString(await response.text(), "text/html");
      const next = doc.querySelector(SELECTOR);
      if (!next) {
        throw new Error("no listing in response");
      }
      current.innerHTML = next.innerHTML;
      current.classList.remove("listing--swap");
      void current.offsetWidth;
      current.classList.add("listing--swap");
      clearTimeout(swapTimer);
      swapTimer = setTimeout(() => current.classList.remove("listing--swap"), 1000);
      document.title = doc.title;
      if (mode === "push") {
        history.pushState({ listing: true }, "", url);
      }
      current.querySelector(focusSelector || "")?.focus({ preventScroll: true });
      if (focusSelector === ".pagination__link--active") {
        current.scrollIntoView({ block: "start" });
      }
    } catch (error) {
      if (error.name !== "AbortError") {
        location.assign(url);
      }
      return;
    } finally {
      current.classList.remove("listing--loading");
      current.removeAttribute("aria-busy");
    }
  };

  document.addEventListener("click", (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const link = event.target.closest(".sort__link, .pagination__link");
    if (!link || !link.closest(SELECTOR) || link.target) {
      return;
    }
    event.preventDefault();
    if (link.href === location.href) {
      return;
    }
    load(link.href, "push", link.matches(".sort__link") ? ".sort__link--active" : ".pagination__link--active");
  });

  window.addEventListener("popstate", () => {
    if (region()) {
      load(location.href, "pop");
    }
  });
})();

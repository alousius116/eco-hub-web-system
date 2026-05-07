(function () {
  const drawer = document.getElementById("mdrawer");
  const overlay = document.querySelector(".mdrawer-overlay");
  const openBtn = document.querySelector(".mdrawer-openbtn");
  const closeBtns = document.querySelectorAll("[data-mdrawer-close]");

  if (!drawer || !openBtn) return;

  function openDrawer() {
    drawer.classList.add("open");
    document.body.classList.add("mdrawer-open"); // ✅ match CSS
    openBtn.setAttribute("aria-expanded", "true");
    drawer.setAttribute("aria-hidden", "false");
  }

  function closeDrawer() {
    drawer.classList.remove("open");
    document.body.classList.remove("mdrawer-open"); // ✅ match CSS
    openBtn.setAttribute("aria-expanded", "false");
    drawer.setAttribute("aria-hidden", "true");
  }

  openBtn.addEventListener("click", function (e) {
    e.preventDefault();
    drawer.classList.contains("open") ? closeDrawer() : openDrawer();
  });

  if (overlay) overlay.addEventListener("click", closeDrawer);
  closeBtns.forEach(btn => btn.addEventListener("click", closeDrawer));

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeDrawer();
  });
})();





// assets/js/app.js
(function () {
  const btnOpen = document.getElementById("btnDrawer");
  const btnClose = document.getElementById("btnDrawerClose");
  const drawer = document.getElementById("drawer");
  const overlay = document.getElementById("drawerOverlay");

  function openDrawer() {
    if (!drawer || !overlay) return;
    drawer.classList.add("open");
    overlay.hidden = false;
    drawer.setAttribute("aria-hidden", "false");
    document.body.classList.add("noScroll");
  }

  function closeDrawer() {
    if (!drawer || !overlay) return;
    drawer.classList.remove("open");
    overlay.hidden = true;
    drawer.setAttribute("aria-hidden", "true");
    document.body.classList.remove("noScroll");
  }

  btnOpen && btnOpen.addEventListener("click", openDrawer);
  btnClose && btnClose.addEventListener("click", closeDrawer);
  overlay && overlay.addEventListener("click", closeDrawer);
})();
// assets/js/app.js

document.addEventListener("DOMContentLoaded", () => {
  const btnOpen = document.getElementById("btnDrawer");
  const btnClose = document.getElementById("btnCloseDrawer");
  const drawer = document.getElementById("drawer");
  const overlay = document.getElementById("drawerOverlay");

  if (!btnOpen || !drawer || !overlay) return;

  // open drawer
  btnOpen.addEventListener("click", () => {
    drawer.classList.add("open");
    overlay.style.display = "block";
    document.body.classList.add("noScroll");
  });

  // close drawer
  const closeDrawer = () => {
    drawer.classList.remove("open");
    overlay.style.display = "none";
    document.body.classList.remove("noScroll");
  };

  overlay.addEventListener("click", closeDrawer);
  if (btnClose) btnClose.addEventListener("click", closeDrawer);
});

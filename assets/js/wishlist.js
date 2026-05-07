document.addEventListener("click", async (e) => {
  const btn = e.target.closest(".heartBtn");
  if (!btn) return;

  // 防止点心心时跳去 detail
  e.preventDefault();
  e.stopPropagation();

  if (!window.ECOHUB?.isLogin) {
    alert("Please login to use wishlist.");
    window.location.href = (window.ECOHUB?.base || "") + "/auth/login.php";
    return;
  }

  const itemId = btn.dataset.itemId;
  if (!itemId) return;

  btn.disabled = true;

  try {
    const fd = new FormData();
    fd.append("item_id", itemId);

    const res = await fetch((window.ECOHUB.base || "") + "/wishlist/toggle.php", {
      method: "POST",
      body: fd
    });

    const data = await res.json();

    if (!data.ok) {
      alert(data.message || "Failed.");
      return;
    }

    // UI update
    if (data.action === "added") btn.classList.add("active");
    if (data.action === "removed") btn.classList.remove("active");

  } catch (err) {
    alert("Network error.");
  } finally {
    btn.disabled = false;
  }
}, true);

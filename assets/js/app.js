(() => {
  const toastRegion = document.querySelector("[data-toast-region]");
  let toastTimer;

  const showToast = (message) => {
    if (!toastRegion || !message) return;
    toastRegion.textContent = message;
    toastRegion.classList.add("is-visible");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(
      () => toastRegion.classList.remove("is-visible"),
      2800,
    );
  };

  document.querySelectorAll("[data-toast]").forEach((element) => {
    element.addEventListener("click", (event) => {
      if (element.tagName === "A") event.preventDefault();
      showToast(element.dataset.toast);
    });
  });

  const sidebarToggle = document.querySelector("[data-sidebar-toggle]");
  const sidebarClose = document.querySelector("[data-sidebar-close]");
  const closeSidebar = () => document.body.classList.remove("sidebar-is-open");

  sidebarToggle?.addEventListener("click", () =>
    document.body.classList.toggle("sidebar-is-open"),
  );
  sidebarClose?.addEventListener("click", closeSidebar);
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeSidebar();
  });

  const tableSearch = document.querySelector("[data-table-search]");
  const tableBody = document.querySelector("[data-table-body]");
  if (tableSearch && tableBody) {
    const rows = [...tableBody.querySelectorAll("tr")];
    tableSearch.addEventListener("input", () => {
      const query = tableSearch.value.trim().toLocaleLowerCase("vi");
      let visibleCount = 0;
      rows.forEach((row) => {
        const visible = row.textContent.toLocaleLowerCase("vi").includes(query);
        row.hidden = !visible;
        if (visible) visibleCount += 1;
      });
      let emptyRow = tableBody.querySelector("[data-empty-row]");
      if (!visibleCount && !emptyRow) {
        emptyRow = document.createElement("tr");
        emptyRow.dataset.emptyRow = "";
        const cell = document.createElement("td");
        cell.colSpan = rows[0]?.children.length || 1;
        cell.textContent = "Không tìm thấy kết quả phù hợp.";
        emptyRow.append(cell);
        tableBody.append(emptyRow);
      } else if (visibleCount && emptyRow) {
        emptyRow.remove();
      }
    });
  }

  const listSearch = document.querySelector("[data-list-search]");
  if (listSearch) {
    const items = [...document.querySelectorAll("[data-search-item]")];
    listSearch.addEventListener("input", () => {
      const query = listSearch.value.trim().toLocaleLowerCase("vi");
      items.forEach((item) => {
        item.hidden = !item.textContent.toLocaleLowerCase("vi").includes(query);
      });
    });
  }

  document.querySelectorAll("[data-password-toggle]").forEach((toggle) => {
    const targetId = toggle.dataset.passwordTarget;
    const input = targetId ? document.getElementById(targetId) : null;
    if (!input) return;

    toggle.addEventListener("click", () => {
      const isPassword = input.type === "password";
      input.type = isPassword ? "text" : "password";
      toggle.setAttribute("aria-pressed", String(isPassword));
      toggle.setAttribute(
        "aria-label",
        isPassword ? "Ẩn mật khẩu" : "Hiện mật khẩu",
      );
    });
  });
})();

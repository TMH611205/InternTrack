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
  const normalizeSearch = (value) =>
    value
      .toLocaleLowerCase("vi")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/đ/g, "d")
      .trim();

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
    const tableCount = document.querySelector("[data-table-count]");
    const updateTableCount = (visibleCount) => {
      if (!tableCount) return;
      tableCount.textContent =
        visibleCount === rows.length
          ? `${rows.length} mục · Theo dữ liệu hiện tại`
          : `${visibleCount} / ${rows.length} mục`;
    };
    updateTableCount(rows.length);
    tableSearch.addEventListener("input", () => {
      const query = normalizeSearch(tableSearch.value);
      let visibleCount = 0;
      rows.forEach((row) => {
        const visible = normalizeSearch(row.textContent).includes(query);
        row.hidden = !visible;
        if (visible) visibleCount += 1;
      });
      updateTableCount(visibleCount);
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
      const query = normalizeSearch(listSearch.value);
      items.forEach((item) => {
        item.hidden = !normalizeSearch(item.textContent).includes(query);
      });
    });
  }

  const opportunitySearch = document.querySelector("[data-opportunity-search]");
  const opportunitySortButtons = [
    ...document.querySelectorAll("[data-sort-mode]"),
  ];
  const opportunityList = document.querySelector("[data-opportunity-list]");
  if (opportunitySearch && opportunitySortButtons.length && opportunityList) {
    const cards = [
      ...opportunityList.querySelectorAll("[data-opportunity-card]"),
    ];
    const emptyMessage = document.querySelector("[data-opportunity-empty]");
    const resultCount = document.querySelector("[data-opportunity-count]");

    const updateOpportunities = () => {
      const query = normalizeSearch(opportunitySearch.value);
      const sortedCards = [...cards].sort((left, right) => {
        const sortMode = opportunitySortButtons.find(
          (button) => button.getAttribute("aria-pressed") === "true",
        )?.dataset.sortMode;
        if (sortMode === "deadline") {
          const leftDeadline = left.dataset.deadline || "9999-12-31";
          const rightDeadline = right.dataset.deadline || "9999-12-31";
          return leftDeadline.localeCompare(rightDeadline);
        }
        return (
          Number(right.dataset.matchScore || 0) -
          Number(left.dataset.matchScore || 0)
        );
      });

      let visibleCount = 0;
      sortedCards.forEach((card) => {
        const visible = normalizeSearch(card.textContent).includes(query);
        card.hidden = !visible;
        if (visible) visibleCount += 1;
        opportunityList.append(card);
      });
      if (resultCount)
        resultCount.textContent = `${visibleCount} / ${cards.length} cơ hội`;
      if (emptyMessage) emptyMessage.hidden = visibleCount > 0;
    };

    opportunitySearch.addEventListener("input", updateOpportunities);
    opportunitySortButtons.forEach((button) => {
      button.addEventListener("click", () => {
        opportunitySortButtons.forEach((option) => {
          const selected = option === button;
          option.setAttribute("aria-pressed", String(selected));
          option.classList.toggle("is-selected", selected);
        });
        updateOpportunities();
      });
    });
    updateOpportunities();
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

  document.querySelectorAll("[data-otp-inputs]").forEach((group) => {
    const form = group.closest("form");
    const valueField = form?.querySelector("[data-otp-value]");
    const digits = [...group.querySelectorAll("[data-otp-digit]")];
    if (!valueField || digits.length !== 6) return;

    const syncValue = () => {
      valueField.value = digits.map((input) => input.value).join("");
    };

    const fillDigits = (start, value) => {
      const numbers = value.replace(/\D/g, "").slice(0, digits.length - start);
      [...numbers].forEach((number, offset) => {
        digits[start + offset].value = number;
      });
      syncValue();
      const nextIndex = Math.min(start + numbers.length, digits.length - 1);
      digits[nextIndex]?.focus();
    };

    digits.forEach((input, index) => {
      input.addEventListener("input", () => {
        const numbers = input.value.replace(/\D/g, "");
        if (numbers.length > 1) {
          fillDigits(index, numbers);
          return;
        }
        input.value = numbers;
        syncValue();
        if (numbers && index < digits.length - 1) digits[index + 1].focus();
      });

      input.addEventListener("keydown", (event) => {
        if (event.key === "Backspace" && !input.value && index > 0) {
          digits[index - 1].focus();
        } else if (event.key === "ArrowLeft" && index > 0) {
          digits[index - 1].focus();
        } else if (event.key === "ArrowRight" && index < digits.length - 1) {
          digits[index + 1].focus();
        }
      });

      input.addEventListener("paste", (event) => {
        event.preventDefault();
        fillDigits(index, event.clipboardData?.getData("text") || "");
      });
    });

    form.addEventListener("submit", syncValue);
  });

  document.querySelectorAll("[data-otp-resend]").forEach((form) => {
    const button = form.querySelector("[data-otp-resend-button]");
    const countdown = form.querySelector("[data-otp-resend-countdown]");
    let remaining = Number.parseInt(form.dataset.remaining || "0", 10);
    if (!button || !countdown || !Number.isFinite(remaining)) return;

    const updateCountdown = () => {
      button.disabled = remaining > 0;
      countdown.textContent =
        remaining > 0
          ? `Có thể gửi lại sau ${remaining} giây.`
          : "Bạn có thể yêu cầu mã mới.";
    };

    updateCountdown();
    if (remaining > 0) {
      const timer = window.setInterval(() => {
        remaining -= 1;
        updateCountdown();
        if (remaining <= 0) window.clearInterval(timer);
      }, 1000);
    }
  });
})();

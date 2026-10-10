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

  const boardFilter = document.querySelector("[data-board-filter]");
  if (boardFilter) {
    const boardSearch = boardFilter.querySelector("[data-board-search]");
    const boardStudent = boardFilter.querySelector("[data-board-student]");
    const boardAttention = boardFilter.querySelector("[data-board-attention]");
    const boardCount = boardFilter.querySelector("[data-board-count]");
    const cards = [...document.querySelectorAll(".task-card")];
    const applyBoardFilter = () => {
      const query = normalizeSearch(boardSearch.value);
      let total = 0;
      cards.forEach((card) => {
        const visible =
          (!boardStudent.value ||
            card.dataset.taskStudent === boardStudent.value) &&
          (!boardAttention.checked ||
            card.dataset.taskStatus === "submitted") &&
          normalizeSearch(card.textContent).includes(query);
        card.hidden = !visible;
        if (visible) total += 1;
      });
      document.querySelectorAll(".board-column").forEach((column) => {
        const visible = column.querySelectorAll(
          ".task-card:not([hidden])",
        ).length;
        const badge = column.querySelector(".board-heading span");
        if (badge) badge.textContent = visible;
        const empty = column.querySelector("[data-board-empty]");
        if (empty) empty.hidden = visible > 0;
      });
      boardCount.textContent = `${total} / ${cards.length} nhiệm vụ`;
    };
    [boardSearch, boardStudent, boardAttention].forEach((control) =>
      control.addEventListener(
        control === boardSearch ? "input" : "change",
        applyBoardFilter,
      ),
    );
    applyBoardFilter();
  }

  document.querySelectorAll("[data-major-filter]").forEach((button) =>
    button.addEventListener("click", () => {
      const search = document.querySelector("[data-table-search]");
      if (!search) return;
      search.value = button.dataset.majorFilter;
      search.dispatchEvent(new Event("input"));
      search.scrollIntoView({ behavior: "smooth", block: "center" });
    }),
  );

  const majorSearch = document.querySelector("[data-major-search]");
  if (majorSearch) {
    const majorRows = [
      ...document.querySelectorAll("[data-major-list] .major-row"),
    ];
    const majorEmpty = document.querySelector("[data-major-empty]");
    majorSearch.addEventListener("input", () => {
      const query = normalizeSearch(majorSearch.value);
      let visibleCount = 0;
      majorRows.forEach((row) => {
        const visible = normalizeSearch(
          row.querySelector(".major-row-info strong").textContent,
        ).includes(query);
        row.hidden = !visible;
        if (visible) visibleCount += 1;
      });
      if (majorEmpty) majorEmpty.hidden = visibleCount > 0;
    });
  }

  document.querySelectorAll("[data-ai-match-form]").forEach((form) =>
    form.addEventListener("submit", () => {
      const button = form.querySelector("[data-ai-match-button]");
      if (button) {
        button.disabled = true;
        button.textContent = "AI đang đọc CV… (có thể mất vài chục giây)";
      }
    }),
  );

  document.addEventListener("submit", (event) => {
    const button = event.target.querySelector?.("[data-ai-submit]");
    if (button) {
      button.disabled = true;
      button.textContent = "AI đang phân tích… (có thể mất vài chục giây)";
    }
  });

  const aiChat = document.querySelector("[data-ai-chat]");
  if (aiChat) {
    const panel = aiChat.querySelector("[data-ai-chat-panel]");
    const toggle = aiChat.querySelector("[data-ai-chat-toggle]");
    const log = aiChat.querySelector("[data-ai-chat-log]");
    const form = aiChat.querySelector("[data-ai-chat-form]");
    const input = form.querySelector("input");
    const suggest = aiChat.querySelector("[data-ai-chat-suggest]");
    const storeKey = "interntrack-ai-chat";
    let history = [];
    try {
      history = JSON.parse(sessionStorage.getItem(storeKey) || "[]");
    } catch (error) {
      history = [];
    }

    const escapeHtml = (text) =>
      text.replace(
        /[&<>"']/g,
        (char) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
          })[char],
      );
    // [[id|tên vị trí]] -> liên kết tới trang chi tiết; nội dung được escape trước khi chèn.
    const renderMessage = (text) =>
      escapeHtml(text)
        .replace(
          /\[\[(\d+)\|([^\]]+)\]\]/g,
          '<a href="?page=student/internship-detail&amp;id=$1">$2</a>',
        )
        .replace(/\n/g, "<br>");
    const addBubble = (role, text) => {
      const isUser = role === "user";
      const row = document.createElement("div");
      row.className = `ai-chat-row ai-chat-row--${isUser ? "user" : "bot"}`;
      if (!isUser) {
        const avatar = document.createElement("span");
        avatar.className = "ai-chat-avatar";
        avatar.innerHTML =
          '<img src="assets/images/logo-mark.svg" alt="" width="16" height="10">';
        row.appendChild(avatar);
      }
      const bubble = document.createElement("div");
      bubble.className = `ai-chat-msg ai-chat-msg--${isUser ? "user" : "bot"}`;
      if (isUser) bubble.textContent = text;
      else bubble.innerHTML = renderMessage(text);
      row.appendChild(bubble);
      log.appendChild(row);
      log.scrollTop = log.scrollHeight;
      return bubble;
    };
    const save = () => {
      try {
        sessionStorage.setItem(storeKey, JSON.stringify(history.slice(-16)));
      } catch (error) {
        /* bỏ qua nếu trình duyệt chặn lưu trữ */
      }
    };
    const setOpen = (open) => {
      panel.hidden = !open;
      aiChat.classList.toggle("is-open", open);
      toggle.setAttribute("aria-expanded", String(open));
      if (open) input.focus();
    };

    if (history.length) {
      history.forEach((turn) => addBubble(turn.role, turn.text));
      suggest.hidden = true;
    } else {
      addBubble(
        "model",
        "Xin chào! Mình đã có thể xem CV và hồ sơ của bạn. Bạn muốn mình gợi ý vị trí thực tập nào phù hợp?",
      );
    }

    toggle.addEventListener("click", () => setOpen(panel.hidden));
    aiChat
      .querySelector("[data-ai-chat-close]")
      .addEventListener("click", () => setOpen(false));
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !panel.hidden) setOpen(false);
    });
    suggest.querySelectorAll("button").forEach((button) =>
      button.addEventListener("click", () => {
        input.value = button.textContent;
        form.requestSubmit();
      }),
    );

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const message = input.value.trim();
      if (!message) return;
      input.value = "";
      suggest.hidden = true;
      addBubble("user", message);
      const pending = addBubble("model", "");
      pending.innerHTML =
        '<span class="ai-typing" aria-label="Đang trả lời"><i></i><i></i><i></i></span>';
      pending.classList.add("is-pending");
      form.querySelector("button").disabled = true;
      try {
        const body = new URLSearchParams({
          action: "ai_chat",
          _csrf: aiChat.dataset.csrf,
          message,
          history: JSON.stringify(history.slice(-8)),
        });
        const response = await fetch(aiChat.dataset.endpoint, {
          method: "POST",
          body,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.reply)
          throw new Error(
            data.error || "Không nhận được phản hồi. Vui lòng thử lại.",
          );
        pending.classList.remove("is-pending");
        pending.innerHTML = renderMessage(data.reply);
        history.push(
          { role: "user", text: message },
          { role: "model", text: data.reply },
        );
        save();
      } catch (error) {
        pending.classList.remove("is-pending");
        pending.classList.add("is-error");
        pending.textContent = error.message;
      } finally {
        form.querySelector("button").disabled = false;
        log.scrollTop = log.scrollHeight;
        input.focus();
      }
    });
  }

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

// Đồng hồ ngày giờ ở thanh trên cùng (múi giờ Việt Nam).
(() => {
  const dateEl = document.querySelector("[data-clock-date]");
  const timeEl = document.querySelector("[data-clock-time]");
  if (!dateEl || !timeEl) return;
  const zone = { timeZone: "Asia/Ho_Chi_Minh" };
  const dateFormat = new Intl.DateTimeFormat("vi-VN", {
    ...zone,
    weekday: "long",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
  const timeFormat = new Intl.DateTimeFormat("vi-VN", {
    ...zone,
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  });
  const tick = () => {
    const now = new Date();
    const date = dateFormat.format(now);
    dateEl.textContent = date.charAt(0).toUpperCase() + date.slice(1);
    timeEl.textContent = timeFormat.format(now);
  };
  tick();
  window.setInterval(tick, 1000);
})();

// Nút trỏ tới bảng thao tác (<details id="...">) sẽ mở bảng đó rồi cuộn tới.
(() => {
  const openPanel = (hash) => {
    if (!hash || hash.length < 2) return false;
    const target = document.getElementById(decodeURIComponent(hash.slice(1)));
    if (!target) return false;
    if (target.tagName === "DIALOG") {
      if (typeof target.showModal === "function" && !target.open)
        target.showModal();
      target
        .querySelector("input:not([type=hidden]), textarea, select")
        ?.focus();
      return true;
    }
    if (target.tagName !== "DETAILS") return false;
    target.open = true;
    target.scrollIntoView({ behavior: "smooth", block: "start" });
    target
      .querySelector("input:not([type=hidden]), textarea, select")
      ?.focus({ preventScroll: true });
    return true;
  };
  document.addEventListener("click", (event) => {
    const link = event.target.closest('a[href^="#"]');
    if (link && openPanel(link.getAttribute("href"))) event.preventDefault();
  });
  window.addEventListener("load", () => openPanel(window.location.hash));
})();

// Chuông thông báo: mở/đóng bảng thả xuống, đóng khi bấm ra ngoài hoặc nhấn Escape.
(() => {
  const root = document.querySelector("[data-notification-root]");
  if (!root) return;
  const toggle = root.querySelector("[data-notification-toggle]");
  const panel = root.querySelector("[data-notification-panel]");
  const setOpen = (open) => {
    panel.hidden = !open;
    toggle.setAttribute("aria-expanded", String(open));
  };
  toggle.addEventListener("click", () => setOpen(panel.hidden));
  document.addEventListener("click", (event) => {
    if (!root.contains(event.target)) setOpen(false);
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setOpen(false);
  });
})();

// Form tạo tài khoản: chỉ hiện các trường theo vai trò đang chọn.
(() => {
  const form = document.querySelector("[data-role-form]");
  const select = form?.querySelector("[data-role-select]");
  if (!form || !select) return;
  const sync = () => {
    form.querySelectorAll("[data-role-field]").forEach((field) => {
      field.hidden = field.dataset.roleField !== select.value;
    });
    form.querySelectorAll("[data-required-for]").forEach((input) => {
      input.required = input.dataset.requiredFor === select.value;
    });
  };
  select.addEventListener("change", sync);
  sync();
})();

// Hộp thoại nổi: mở bằng [data-dialog-open], đóng bằng [data-dialog-close], bấm nền tối hoặc Escape.
(() => {
  document.addEventListener("click", (event) => {
    const opener = event.target.closest("[data-dialog-open]");
    if (opener) {
      const dialog = document.getElementById(opener.dataset.dialogOpen);
      if (dialog && typeof dialog.showModal === "function") {
        dialog.showModal();
        dialog
          .querySelector("input:not([type=hidden]), textarea, select")
          ?.focus();
      }
      return;
    }
    if (event.target.closest("[data-dialog-close]")) {
      event.target.closest("dialog")?.close();
      return;
    }
    // Bấm vào vùng nền tối (chính là phần tử dialog) thì đóng.
    if (event.target instanceof HTMLDialogElement && event.target.open)
      event.target.close();
  });
})();

// Nhắn tin trực tiếp: Enter để gửi, ô nhập tự giãn, luôn cuộn xuống tin mới nhất và tự làm mới hội thoại mỗi vài giây.
(() => {
  const root = document.querySelector("[data-chat-root]");
  if (!root) return;
  const thread = () => root.querySelector("[data-chat-thread]");
  const scrollToEnd = () => {
    const box = thread();
    if (box) box.scrollTop = box.scrollHeight;
  };
  scrollToEnd();

  const input = root.querySelector("[data-chat-input]");
  const form = root.querySelector("[data-chat-form]");
  const fileInput = root.querySelector("[data-chat-file]");
  const picked = root.querySelector("[data-chat-picked]");
  const maxBytes = 10 * 1024 * 1024;
  // Hiện tên tệp đã chọn phía trên ô nhập; báo ngay nếu quá 10 MB để người dùng không phải chờ tải lên rồi mới thấy lỗi.
  const showPicked = () => {
    const file = fileInput?.files?.[0];
    if (file && file.size > maxBytes) {
      alert("Tệp vượt quá 10 MB. Hãy chọn tệp nhỏ hơn.");
      fileInput.value = "";
    }
    const current = fileInput?.files?.[0];
    picked.hidden = !current;
    picked.querySelector("[data-chat-picked-name]").textContent = current
      ? current.name
      : "";
  };
  if (fileInput && picked) {
    fileInput.addEventListener("change", showPicked);
    picked
      .querySelector("[data-chat-picked-clear]")
      .addEventListener("click", () => {
        fileInput.value = "";
        showPicked();
      });
  }
  if (input && form) {
    const grow = () => {
      input.style.height = "auto";
      input.style.height = Math.min(input.scrollHeight, 140) + "px";
    };
    input.addEventListener("input", grow);
    input.addEventListener("keydown", (event) => {
      if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        if (input.value.trim() !== "" || fileInput?.files?.length)
          form.requestSubmit();
      }
    });
    form.addEventListener("submit", () => {
      form.querySelector("button[type=submit]").disabled = true;
    });
    input.focus();
  }

  // Làm mới định kỳ: tải lại trang hiện tại ở nền rồi thay danh sách liên hệ và nội dung hội thoại nếu có tin mới.
  const url = root.dataset.chatUrl;
  if (!url) return;
  const refresh = async () => {
    if (document.visibilityState !== "visible") return;
    try {
      const response = await fetch(url, { credentials: "same-origin" });
      if (!response.ok) return;
      const next = new DOMParser().parseFromString(
        await response.text(),
        "text/html",
      );
      const swap = (selector) => {
        const current = root.querySelector(selector);
        const fresh = next.querySelector(selector);
        if (!current || !fresh || current.innerHTML === fresh.innerHTML)
          return false;
        current.innerHTML = fresh.innerHTML;
        return true;
      };
      swap("[data-chat-contacts]");
      const box = thread();
      const nearEnd = box
        ? box.scrollHeight - box.scrollTop - box.clientHeight < 80
        : false;
      if (swap("[data-chat-thread]") && nearEnd) scrollToEnd();
    } catch (error) {
      // Mất mạng tạm thời: bỏ qua, lần sau thử lại.
    }
  };
  setInterval(refresh, 6000);
})();

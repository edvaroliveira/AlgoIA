/**
 * IAProg — app.js
 * Auto-save answers while student types (debounced, AJAX POST).
 */
(function () {
  "use strict";

  // ── Confirmação de ações destrutivas ────────────────────────────────────────
  // Handler inline (onclick/onsubmit) e bloqueado pela CSP: o nonce nao libera
  // atributo de evento. Declaramos a mensagem em data-confirm e confirmamos aqui.
  // Registrado antes de tudo para nao depender de blocos posteriores desta pagina.
  document.addEventListener(
    "click",
    function (event) {
      const trigger = event.target.closest("[data-confirm]");
      if (!trigger || trigger.tagName === "FORM") {
        return;
      }
      if (!window.confirm(trigger.dataset.confirm)) {
        event.preventDefault();
        event.stopPropagation();
      }
    },
    true
  );

  document.addEventListener(
    "submit",
    function (event) {
      const form = event.target;
      if (!form.matches("form[data-confirm]")) {
        return;
      }
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
        event.stopPropagation();
      }
    },
    true
  );

  // ── Menu lateral recolhível (gaveta abaixo de 992px) ───────────────────────
  // Abre pelo ☰; fecha com ESC, clique no fundo, clique em link ou ao voltar
  // para a largura de desktop. Ao fechar, o foco volta ao ☰.
  const drawerToggle = document.querySelector("[aria-controls='menu-lateral']");
  const drawer = document.getElementById("menu-lateral");
  const drawerBackdrop = document.querySelector("[data-drawer-close]");
  if (drawerToggle && drawer) {
    const desktop = window.matchMedia("(min-width: 992px)");
    const setDrawer = function (open, restoreFocus) {
      document.body.classList.toggle("drawer-open", open);
      drawerToggle.setAttribute("aria-expanded", open ? "true" : "false");
      drawerToggle.setAttribute("aria-label", open ? "Fechar menu" : "Abrir menu");
      if (drawerBackdrop) {
        drawerBackdrop.hidden = !open;
      }
      if (open) {
        // Espera o navegador aplicar visibility: visible antes de focar
        window.requestAnimationFrame(function () {
          window.requestAnimationFrame(function () {
            const first = drawer.querySelector("a[href]");
            if (first && isOpen()) {
              first.focus();
            }
          });
        });
      } else if (restoreFocus) {
        drawerToggle.focus();
      }
    };
    const isOpen = function () {
      return document.body.classList.contains("drawer-open");
    };
    drawerToggle.addEventListener("click", function () {
      setDrawer(!isOpen(), true);
    });
    if (drawerBackdrop) {
      drawerBackdrop.addEventListener("click", function () {
        setDrawer(false, true);
      });
    }
    drawer.addEventListener("click", function (event) {
      if (event.target.closest("a[href]") && isOpen()) {
        setDrawer(false, false);
      }
    });
    document.addEventListener("keydown", function (event) {
      if (!isOpen()) {
        return;
      }
      if (event.key === "Escape") {
        setDrawer(false, true);
        return;
      }
      // Tab fica preso dentro da gaveta enquanto ela está aberta
      if (event.key === "Tab") {
        const links = drawer.querySelectorAll("a[href]");
        if (!links.length) {
          return;
        }
        const first = links[0];
        const last = links[links.length - 1];
        if (event.shiftKey && (document.activeElement === first || !drawer.contains(document.activeElement))) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !drawer.contains(document.activeElement))) {
          event.preventDefault();
          first.focus();
        }
      }
    });
    desktop.addEventListener("change", function (event) {
      if (event.matches && isOpen()) {
        setDrawer(false, false);
      }
    });
  }

  // ── Estado de envio dos formulários POST ───────────────────────────────────
  // Evita clique duplo (ex.: Prática, que espera a correção por IA): os botões
  // do formulário ficam desabilitados e o botão usado mostra data-submitting-text
  // (padrão "Enviando…"). Escuta no documento, depois dos handlers do próprio
  // formulário, e ignora envio cancelado (confirmação negada, validação).
  // Desabilita só no próximo ciclo: botão desabilitado durante o evento submit
  // sairia dos dados enviados (formaction/name do botão usado).
  document.addEventListener("submit", function (event) {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() !== "post") {
      return;
    }
    const submitter = event.submitter;
    window.setTimeout(function () {
      form.setAttribute("aria-busy", "true");
      Array.prototype.forEach.call(form.elements, function (el) {
        if (el.type !== "submit" || el.disabled) {
          return;
        }
        el.disabled = true;
        el.setAttribute("data-submit-locked", "");
        if (el === submitter && el.tagName === "BUTTON") {
          el.setAttribute("data-original-text", el.textContent);
          el.textContent = el.getAttribute("data-submitting-text") || "Enviando…";
        }
      });
    }, 0);
  });

  // Voltar pelo histórico (bfcache) restaura a página com os botões travados.
  window.addEventListener("pageshow", function (event) {
    if (!event.persisted) {
      return;
    }
    document.querySelectorAll("[data-submit-locked]").forEach(function (el) {
      el.disabled = false;
      el.removeAttribute("data-submit-locked");
      if (el.hasAttribute("data-original-text")) {
        el.textContent = el.getAttribute("data-original-text");
        el.removeAttribute("data-original-text");
      }
      if (el.form) {
        el.form.removeAttribute("aria-busy");
      }
    });
  });

  // ── Avisos (flash) fecháveis ──────────────────────────────────────────────
  document.addEventListener("click", function (event) {
    const close = event.target.closest("[data-dismiss-alert]");
    if (close) {
      const alertBox = close.closest(".alert");
      if (alertBox) {
        alertBox.remove();
      }
    }
  });

  // ── Tabelas: rótulo de cada célula a partir do cabeçalho ───────────────────
  // No celular (< 768px) as listas viram cartões e o rótulo aparece antes de
  // cada valor (CSS: td::before { content: attr(data-label) }). Tabelas largas
  // (.table--wide) só rolam na horizontal e ficam de fora.
  document.querySelectorAll("table.table:not(.table--wide)").forEach(function (table) {
    const headRow = table.tHead && table.tHead.rows[0];
    if (!headRow) {
      return;
    }
    const labels = [];
    Array.prototype.forEach.call(headRow.cells, function (th) {
      const text = th.querySelector("input[type='checkbox']")
        ? "Selecionar"
        : th.textContent.replace(/\s+/g, " ").trim();
      for (let i = 0; i < (th.colSpan || 1); i++) {
        labels.push(text);
      }
    });
    Array.prototype.forEach.call(table.tBodies, function (tbody) {
      Array.prototype.forEach.call(tbody.rows, function (row) {
        let col = 0;
        Array.prototype.forEach.call(row.cells, function (cell) {
          const span = cell.colSpan || 1;
          if (span === 1 && cell.tagName === "TD" && labels[col] && !cell.hasAttribute("data-label")) {
            cell.setAttribute("data-label", labels[col]);
          }
          col += span;
        });
      });
    });
    table.classList.add("table--stack");
  });

  // ── Avatar: refletir nome do arquivo escolhido ───────────────────────────────
  // Primeiro de tudo, para nao depender de blocos anteriores nesta pagina.
  const avatarInput = document.getElementById("avatar");
  if (avatarInput) {
    const avatarLabel = document.querySelector(".avatar-uploader__filename");
    avatarInput.addEventListener("change", function () {
      if (!avatarLabel) {
        return;
      }
      const file = avatarInput.files && avatarInput.files[0];
      avatarLabel.textContent = file ? file.name : avatarLabel.dataset.placeholder;
    });
  }

  // ── Anel de pontuação: preenche via CSSOM (setProperty é permitido pela CSP) ─
  document.querySelectorAll(".score-ring").forEach(function (ring) {
    var score = parseFloat(ring.dataset.score) || 0;
    var max = parseFloat(ring.dataset.max) || 0;
    var pct = max > 0 ? Math.max(0, Math.min(100, (score / max) * 100)) : 0;
    var color =
      pct >= 60
        ? "var(--success)"
        : pct >= 40
          ? "var(--warning)"
          : "var(--error)";
    ring.style.setProperty("--pct", pct.toFixed(1));
    ring.style.setProperty("--ring-color", color);
  });

  // ── Barra de proporção dos overview-cards (setProperty é permitido pela CSP) ─
  document.querySelectorAll(".overview-card__ratio").forEach(function (el) {
    var value = parseFloat(el.dataset.value) || 0;
    var total = parseFloat(el.dataset.total) || 0;
    var pct = total > 0 ? Math.max(0, Math.min(100, (value / total) * 100)) : 0;
    el.style.setProperty("--fill", pct.toFixed(1));
    var label = el.querySelector(".overview-card__ratio-label");
    if (label) {
      label.textContent = Math.round(pct) + "% da base";
    }
  });

  // ── Auto-save ──────────────────────────────────────────────────────────────
  const textareas = document.querySelectorAll("textarea[data-question]");

  textareas.forEach(function (ta) {
    var timer = null;
    var statusEl = document.getElementById("status-" + ta.dataset.question);

    ta.addEventListener("input", function () {
      clearTimeout(timer);
      if (statusEl) statusEl.textContent = "Aguardando...";

      timer = setTimeout(function () {
        saveAnswer(ta, statusEl);
      }, 1200);
    });
  });

  function saveAnswer(ta, statusEl) {
    var attemptId = ta.dataset.attempt;
    var questionId = ta.dataset.question;
    var csrf = ta.dataset.csrf;
    var saveUrl = ta.dataset.saveUrl;
    var answer = ta.value.trim();

    if (!answer || !attemptId || !saveUrl) return;

    var body = new URLSearchParams({
      _csrf_token: csrf,
      question_id: questionId,
      answer: ta.value,
    });

    fetch(saveUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(function (r) {
        if (!r.ok) {
          throw new Error("autosave_failed");
        }

        return r.json();
      })
      .then(function (data) {
        if (statusEl) {
          statusEl.textContent = data.ok
            ? "✓ Salvo automaticamente"
            : "Erro ao salvar";
        }
      })
      .catch(function () {
        if (statusEl) statusEl.textContent = "Sem conexão – rascunho não salvo";
      });
  }

  // ── Uppercase turma key fields ─────────────────────────────────────────────
  document.querySelectorAll(".form-input--key").forEach(function (el) {
    el.addEventListener("input", function () {
      var pos = el.selectionStart;
      el.value = el.value.toUpperCase();
      el.setSelectionRange(pos, pos);
    });
  });

  // ── Auto-dismiss alerts after 6 s ─────────────────────────────────────────
  document.querySelectorAll(".alert--success").forEach(function (el) {
    setTimeout(function () {
      el.style.transition = "opacity .4s";
      el.style.opacity = "0";
      setTimeout(function () {
        el.remove();
      }, 400);
    }, 6000);
  });

  // ── Select-all batch checkboxes ──────────────────────────────────────────
  document.querySelectorAll("[data-select-all]").forEach(function (toggle) {
    var group = toggle.getAttribute("data-select-all");

    if (!group) {
      return;
    }

    var items = Array.from(
      document.querySelectorAll('[data-select-item="' + group + '"]'),
    );

    if (!items.length) {
      return;
    }

    var controlledButtons = Array.from(
      document.querySelectorAll('[data-requires-selection="' + group + '"]'),
    );
    var counterEls = Array.from(
      document.querySelectorAll('[data-selection-count="' + group + '"]'),
    );
    var breakdownEls = Array.from(
      document.querySelectorAll('[data-selection-breakdown="' + group + '"]'),
    );
    var compatibilityEls = Array.from(
      document.querySelectorAll(
        '[data-selection-compatibility="' + group + '"]',
      ),
    );
    var rows = items.map(function (item) {
      return item.closest("tr");
    });
    var activeAllowedStates = [];
    var pendingSubmitButton = null;
    var form = toggle.form || toggle.closest("form");

    var escapeHtml = function (value) {
      return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
    };

    var buildBadgeMarkup = function (label, variant) {
      return (
        '<span class="badge badge--' +
        escapeHtml(variant) +
        '">' +
        escapeHtml(label) +
        "</span>"
      );
    };

    var getSelectedItems = function () {
      return items.filter(function (item) {
        return item.checked;
      });
    };

    var getCompatibilityCounts = function (selectedItems, allowedStates) {
      if (!allowedStates.length) {
        return {
          compatibleCount: selectedItems.length,
          ignoredCount: 0,
        };
      }

      var compatibleCount = selectedItems.filter(function (item) {
        var itemState = item.getAttribute("data-item-state") || "";
        return allowedStates.indexOf(itemState) !== -1;
      }).length;

      return {
        compatibleCount: compatibleCount,
        ignoredCount: selectedItems.length - compatibleCount,
      };
    };

    var renderCompatibility = function (selectedItems, allowedStates) {
      compatibilityEls.forEach(function (el) {
        if (!selectedItems.length || !allowedStates.length) {
          el.innerHTML = "";
          return;
        }

        var counts = getCompatibilityCounts(selectedItems, allowedStates);
        var parts = [
          buildBadgeMarkup(counts.compatibleCount + " compatíveis", "success"),
        ];

        if (counts.ignoredCount > 0) {
          parts.push(
            buildBadgeMarkup(counts.ignoredCount + " ignorados", "warning"),
          );
        }

        el.innerHTML = parts.join("");
      });
    };

    var clearRowHighlights = function () {
      rows.forEach(function (row) {
        if (!row) {
          return;
        }

        row.classList.remove(
          "table-row--compatible",
          "table-row--incompatible",
        );
      });
    };

    var syncToggle = function () {
      var selectedItems = getSelectedItems();
      var checkedCount = selectedItems.length;

      toggle.checked = checkedCount === items.length;
      toggle.indeterminate = checkedCount > 0 && checkedCount < items.length;

      counterEls.forEach(function (el) {
        var label = checkedCount === 1 ? "selecionado" : "selecionados";
        el.innerHTML = buildBadgeMarkup(checkedCount + " " + label, "neutral");
      });

      breakdownEls.forEach(function (el) {
        if (!checkedCount) {
          el.innerHTML = "";
          return;
        }

        var countsByLabel = {};

        selectedItems.forEach(function (item) {
          var stateLabel = item.getAttribute("data-item-state-label") || "";
          if (!stateLabel) {
            return;
          }

          countsByLabel[stateLabel] = (countsByLabel[stateLabel] || 0) + 1;
        });

        var parts = Object.keys(countsByLabel).map(function (label) {
          return buildBadgeMarkup(countsByLabel[label] + " " + label, "info");
        });

        el.innerHTML = parts.join("");
      });

      controlledButtons.forEach(function (button) {
        var allowedStates = (button.getAttribute("data-allowed-states") || "")
          .split(",")
          .map(function (state) {
            return state.trim();
          })
          .filter(Boolean);

        if (!checkedCount) {
          button.disabled = true;
          return;
        }

        if (!allowedStates.length) {
          button.disabled = false;
          return;
        }

        var hasCompatibleSelection = selectedItems.some(function (item) {
          var itemState = item.getAttribute("data-item-state") || "";
          return allowedStates.indexOf(itemState) !== -1;
        });

        button.disabled = !hasCompatibleSelection;
      });

      renderCompatibility(selectedItems, activeAllowedStates);

      clearRowHighlights();

      if (!activeAllowedStates.length) {
        return;
      }

      selectedItems.forEach(function (item) {
        var row = item.closest("tr");
        if (!row) {
          return;
        }

        var itemState = item.getAttribute("data-item-state") || "";
        if (activeAllowedStates.indexOf(itemState) !== -1) {
          row.classList.add("table-row--compatible");
          return;
        }

        row.classList.add("table-row--incompatible");
      });
    };

    controlledButtons.forEach(function (button) {
      var syncButtonFocus = function (shouldActivate) {
        activeAllowedStates = shouldActivate
          ? (button.getAttribute("data-allowed-states") || "")
              .split(",")
              .map(function (state) {
                return state.trim();
              })
              .filter(Boolean)
          : [];

        syncToggle();
      };

      button.addEventListener("click", function () {
        pendingSubmitButton = button;
        syncButtonFocus(true);
      });

      button.addEventListener("mouseenter", function () {
        syncButtonFocus(true);
      });

      button.addEventListener("focus", function () {
        syncButtonFocus(true);
      });

      button.addEventListener("mouseleave", function () {
        syncButtonFocus(false);
      });

      button.addEventListener("blur", function () {
        syncButtonFocus(false);
      });
    });

    toggle.addEventListener("change", function () {
      items.forEach(function (item) {
        item.checked = toggle.checked;
      });

      toggle.indeterminate = false;
      // Marcar por código não dispara "change" nos itens: atualiza contador e botões aqui.
      syncToggle();
    });

    items.forEach(function (item) {
      item.addEventListener("change", syncToggle);
    });

    // Ao voltar pelo histórico o navegador restaura as caixas marcadas sem
    // disparar "change": sincroniza no carregamento e na restauração (bfcache).
    syncToggle();
    window.addEventListener("pageshow", syncToggle);

    if (form) {
      form.addEventListener("submit", function (event) {
        var submitter = event.submitter || pendingSubmitButton;

        if (!submitter) {
          return;
        }

        var allowedStates = (
          submitter.getAttribute("data-allowed-states") || ""
        )
          .split(",")
          .map(function (state) {
            return state.trim();
          })
          .filter(Boolean);

        if (!allowedStates.length) {
          return;
        }

        var selectedItems = getSelectedItems();
        var counts = getCompatibilityCounts(selectedItems, allowedStates);

        if (!counts.compatibleCount) {
          event.preventDefault();
          activeAllowedStates = allowedStates;
          syncToggle();
          return;
        }

        selectedItems.forEach(function (item) {
          var itemState = item.getAttribute("data-item-state") || "";
          if (allowedStates.indexOf(itemState) === -1) {
            item.checked = false;
          }
        });

        activeAllowedStates = allowedStates;
        syncToggle();
      });
    }

    syncToggle();
  });

  // ── Draft activation form validation ─────────────────────────────────────
  var activationForm = document.querySelector("[data-activation-form]");

  if (activationForm) {
    var feedbackEl = document.querySelector("[data-activation-feedback]");

    activationForm.addEventListener("submit", function (event) {
      var errors = [];
      var questionCount = Number(activationForm.dataset.questionCount || "0");
      var selectedTurmas = activationForm.querySelectorAll(
        'input[name$="[enabled]"]:checked',
      );

      if (questionCount < 1) {
        errors.push(
          "Adicione pelo menos uma questão antes de publicar o exercício.",
        );
      }

      if (!selectedTurmas.length) {
        errors.push("Selecione pelo menos uma turma para publicação.");
      }

      selectedTurmas.forEach(function (input) {
        var prefix = input.name.replace("[enabled]", "");
        var opensAt = activationForm.querySelector(
          'input[name="' + prefix + '[opens_at]"]',
        );
        var closesAt = activationForm.querySelector(
          'input[name="' + prefix + '[closes_at]"]',
        );
        var maxAttempts = activationForm.querySelector(
          'input[name="' + prefix + '[max_attempts]"]',
        );

        if (!opensAt || !closesAt || !opensAt.value || !closesAt.value) {
          errors.push(
            "Preencha abertura e fechamento em todas as turmas selecionadas.",
          );
          return;
        }

        if (new Date(opensAt.value) >= new Date(closesAt.value)) {
          errors.push(
            "A data de fechamento deve ser posterior à abertura em todas as turmas selecionadas.",
          );
        }

        if (maxAttempts && Number(maxAttempts.value) < 0) {
          errors.push("O número de tentativas não pode ser negativo.");
        }
      });

      if (errors.length) {
        errors = Array.from(new Set(errors));
      }

      if (!feedbackEl) {
        return;
      }

      if (errors.length) {
        event.preventDefault();
        feedbackEl.innerHTML = errors
          .map(function (message) {
            return "<div>" + message + "</div>";
          })
          .join("");
        feedbackEl.hidden = false;
        feedbackEl.scrollIntoView({ behavior: "smooth", block: "nearest" });
        return;
      }

      feedbackEl.innerHTML = "";
      feedbackEl.hidden = true;
    });

    activationForm
      .querySelectorAll('input[name$="[enabled]"]')
      .forEach(function (input) {
        input.addEventListener("change", function () {
          if (!feedbackEl) {
            return;
          }

          feedbackEl.innerHTML = "";
          feedbackEl.hidden = true;
        });
      });
  }
})();

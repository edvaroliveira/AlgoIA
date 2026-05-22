/**
 * IAProg — app.js
 * Auto-save answers while student types (debounced, AJAX POST).
 */
(function () {
  "use strict";

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
    var form = toggle.closest("form");

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
    });

    items.forEach(function (item) {
      item.addEventListener("change", syncToggle);
    });

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

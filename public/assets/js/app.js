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

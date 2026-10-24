/**
 * Uso da IA (Admin) — desenha os gráficos com Chart.js a partir do JSON
 * embutido em #ai-usage-data. Arquivo estático: a CSP não permite script inline.
 */
(function () {
  "use strict";

  var source = document.getElementById("ai-usage-data");
  if (!source || typeof window.Chart === "undefined") {
    return;
  }

  var data;
  try {
    data = JSON.parse(source.textContent || "{}");
  } catch (e) {
    return;
  }

  var green = "#1f7a4d";
  var amber = "#c98a12";
  var blue = "#3d6f8e";
  var violet = "#6b4fa0";
  var kindLabel = { grading: "Correção", practice: "Prática", similarity: "Similaridade", python: "Resposta em Python" };
  var kindColor = { grading: green, practice: amber, similarity: blue, python: violet };
  var red = "#b42318";
  var usd = function (v) {
    return "US$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 4 });
  };
  var brl = function (v) {
    return "R$ " + Number(v * (data.usdBrl || 0)).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };
  var moneyTooltip = {
    callbacks: {
      label: function (ctx) {
        var value = ctx.parsed.y !== undefined && ctx.chart.config.type !== "doughnut" && ctx.chart.options.indexAxis !== "y"
          ? ctx.parsed.y
          : (ctx.chart.options.indexAxis === "y" ? ctx.parsed.x : ctx.parsed);
        return (ctx.dataset.label ? ctx.dataset.label + ": " : "") + usd(value) + " (" + brl(value) + ")";
      },
    },
  };
  var draw = function (id, config) {
    var canvas = document.getElementById(id);
    if (canvas) {
      new window.Chart(canvas, config);
    }
  };
  var days = (data.days || []).map(function (d) {
    var parts = d.day.split("-");
    return parts[2] + "/" + parts[1];
  });

  draw("chart-cost-day", {
    type: "bar",
    data: {
      labels: days,
      datasets: [
        { label: "Correção", data: (data.days || []).map(function (d) { return d.grading; }), backgroundColor: green },
        { label: "Prática", data: (data.days || []).map(function (d) { return d.practice; }), backgroundColor: amber },
        { label: "Similaridade", data: (data.days || []).map(function (d) { return d.similarity || 0; }), backgroundColor: blue },
        { label: "Resposta em Python", data: (data.days || []).map(function (d) { return d.python || 0; }), backgroundColor: violet },
      ],
    },
    options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }, plugins: { tooltip: moneyTooltip } },
  });

  draw("chart-calls-day", {
    type: "line",
    data: {
      labels: days,
      datasets: [
        { label: "Chamadas", data: (data.days || []).map(function (d) { return d.calls; }), borderColor: green, backgroundColor: green, tension: 0.25 },
        { label: "Falhas", data: (data.days || []).map(function (d) { return d.errors; }), borderColor: red, backgroundColor: red, tension: 0.25 },
      ],
    },
    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
  });

  var horizontal = function (id, rows) {
    draw(id, {
      type: "bar",
      data: {
        labels: rows.map(function (r) { return r.label; }),
        datasets: [{ label: "Custo", data: rows.map(function (r) { return r.cost; }), backgroundColor: green }],
      },
      options: { indexAxis: "y", responsive: true, plugins: { legend: { display: false }, tooltip: moneyTooltip }, scales: { x: { beginAtZero: true } } },
    });
  };
  horizontal("chart-teachers", data.teachers || []);
  horizontal("chart-turmas", data.turmas || []);

  draw("chart-kinds", {
    type: "doughnut",
    data: {
      labels: (data.kinds || []).map(function (k) { return kindLabel[k.kind] || "Correção"; }),
      datasets: [{ data: (data.kinds || []).map(function (k) { return k.cost; }), backgroundColor: (data.kinds || []).map(function (k) { return kindColor[k.kind] || green; }) }],
    },
    options: { responsive: true, aspectRatio: 2, plugins: { tooltip: moneyTooltip } },
  });
})();

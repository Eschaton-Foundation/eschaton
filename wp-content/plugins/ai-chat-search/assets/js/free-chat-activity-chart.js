/**
 * Free chat activity chart.
 */
(function ($) {
  "use strict";

  function lineGradient(context, rgb) {
    var chartArea = context.chart.chartArea;
    if (!chartArea) {
      return "rgba(" + rgb + ", 0.15)";
    }

    var gradient = context.chart.ctx.createLinearGradient(
      0,
      chartArea.top,
      0,
      chartArea.bottom,
    );
    gradient.addColorStop(0, "rgba(" + rgb + ", 0.28)");
    gradient.addColorStop(1, "rgba(" + rgb + ", 0)");
    return gradient;
  }

  function createDataset(label, data, color, rgb) {
    return {
      label: label,
      data: data,
      borderColor: color,
      backgroundColor: function (context) {
        return lineGradient(context, rgb);
      },
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      borderCapStyle: "round",
      borderJoinStyle: "round",
      pointRadius: 0,
      pointHoverRadius: 5,
      pointHitRadius: 20,
      pointBackgroundColor: "#ffffff",
      pointBorderColor: color,
      pointBorderWidth: 2,
    };
  }

  function initializeChart() {
    var canvas = document.getElementById("airs-free-chat-activity-chart");
    var data =
      typeof purioFreeChatActivityData !== "undefined"
        ? purioFreeChatActivityData
        : null;

    if (!canvas || !data || typeof Chart === "undefined") {
      $(".airs-free-chat-activity-chart").removeClass("is-loading");
      return;
    }

    new Chart(canvas.getContext("2d"), {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [
          createDataset(
            data.strings.conversations,
            data.conversations,
            "#006aff",
            "0, 106, 255",
          ),
          createDataset(
            data.strings.messages,
            data.messages,
            "#10b981",
            "16, 185, 129",
          ),
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: "index",
          intersect: false,
        },
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            backgroundColor: "rgba(0, 0, 0, 0.8)",
            titleColor: "#ffffff",
            bodyColor: "#ffffff",
            padding: 12,
            cornerRadius: 8,
            displayColors: true,
            boxWidth: 12,
            boxHeight: 12,
            boxPadding: 4,
            callbacks: {
              label: function (context) {
                return (
                  " " +
                  (context.dataset.label || "") +
                  ": " +
                  context.parsed.y
                );
              },
            },
          },
        },
        scales: {
          x: {
            grid: {
              display: false,
            },
            ticks: {
              color: "#999999",
              font: {
                size: 11,
              },
              padding: 8,
              maxRotation: 0,
              minRotation: 0,
              autoSkip: true,
              maxTicksLimit: window.innerWidth < 1440 ? 7 : 10,
            },
            border: {
              display: false,
            },
          },
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(160, 160, 160, 0.14)",
              drawTicks: false,
            },
            ticks: {
              color: "#999999",
              font: {
                size: 11,
              },
              padding: 10,
              maxTicksLimit: 5,
              stepSize: 1,
              callback: function (value) {
                return Number.isInteger(value) ? value : null;
              },
            },
            border: {
              display: false,
              dash: [5, 6],
            },
          },
        },
      },
    });

    $(".airs-free-chat-activity-chart").removeClass("is-loading");
  }

  $(initializeChart);
})(jQuery);

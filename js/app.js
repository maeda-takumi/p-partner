/* ==========================
   DOM Ready
========================== */
document.addEventListener('DOMContentLoaded', () => {
  renderContributionCharts();
  initCalendarLinks();
});

/* ==========================
   中央テキスト用プラグイン
========================== */
const centerTextPlugin = {
  id: 'centerText',
  afterDraw(chart, args, opts) {
    const { ctx, chartArea } = chart;
    if (!opts) return;

    const centerX = (chartArea.left + chartArea.right) / 2;
    const centerY = (chartArea.top + chartArea.bottom) / 2;

    const label = opts.label || "";
    const value = opts.value || "";
    let sub = opts.sub || "";

    // ✅ sub（名前列）が長い場合は省略（…）
    sub = fitText(ctx, sub, 120); // 120px以内に収める

    ctx.save();
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillStyle = "#1f2937";

    // 1行目：label
    ctx.font = "600 12px -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial";
    ctx.fillText(label, centerX, centerY - 14);

    // 2行目：value（回数）
    ctx.font = "700 16px -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial";
    ctx.fillText(value, centerX, centerY + 2);

    // 3行目：sub（同率トップの名前列）
    if (sub) {
      ctx.fillStyle = "#6b7280";
      ctx.font = "600 13px -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial";
      ctx.fillText(sub, centerX, centerY + 18);
    }

    ctx.restore();
  }
};

// ✅ 指定pxを超える場合は末尾を「…」で省略する
function fitText(ctx, text, maxWidth) {
  if (!text) return "";
  if (ctx.measureText(text).width <= maxWidth) return text;

  let t = text;
  while (t.length > 1 && ctx.measureText(t + "…").width > maxWidth) {
    t = t.slice(0, -1);
  }
  return t + "…";
}


/* ==========================
   グラフ描画
========================== */
function renderContributionCharts() {
  document.querySelectorAll('.group-card').forEach(card => {
    const dataAttr = card.dataset.contrib;

    // canvas取得（空表示でも使うので先に取る）
    const canvas = card.querySelector('.contrib-chart');
    if (!canvas) return;

    // ✅ すでに空表示があれば消す（再描画時の二重防止）
    const oldEmpty = card.querySelector('.chart-empty');
    if (oldEmpty) oldEmpty.remove();

    // ✅ 既にChart.jsが描画されてたら破棄（再描画対策）
    if (canvas._chartInstance) {
      canvas._chartInstance.destroy();
      canvas._chartInstance = null;
    }

    // データが無いケース
    if (!dataAttr) {
      showEmptyChartMessage(canvas);
      return;
    }

    let contribs;
    try {
      contribs = JSON.parse(dataAttr);
    } catch {
      showEmptyChartMessage(canvas);
      return;
    }

    if (!Array.isArray(contribs) || contribs.length === 0) {
      showEmptyChartMessage(canvas);
      return;
    }

    // ★ グラフサイズ
    canvas.style.display = "";
    canvas.width = 240;
    canvas.height = 240;

    const labels = contribs.map(c => c.name);
    const values = contribs.map(c => c.rate);

    // ✅ 最大値（同率トップ含む）を判定して中央表示用の文言を作る
    const maxRate = Math.max(...contribs.map(c => c.rate));
    const topMembers = contribs.filter(c => c.rate === maxRate);

    let centerLabel = "";
    let centerValue = "";
    let centerSub   = ""; // ←追加（名前列）

    if (topMembers.length === 1) {
      centerLabel = `最多：${topMembers[0].name}`;
      centerValue = `${maxRate}回`;
      centerSub   = "";
    } else {
      centerLabel = `同率トップ`;
      centerValue = `${maxRate}回`;
      centerSub   = topMembers.map(x => x.name).join("・");
    }

    const chart = new Chart(canvas, {
      type: 'doughnut',
      plugins: [centerTextPlugin],
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: generateColors(values.length),
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        cutout: '65%',
        responsive: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12,
              font: { size: 11 }
            }
          },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.label} : ${ctx.raw}回`
            }
          },
          centerText: {
            label: centerLabel,
            value: centerValue,
            sub: centerSub
          }
        }
      }
    });


    // ✅ 破棄できるように保持
    canvas._chartInstance = chart;
  });
}

/* ✅ 空データ表示（円グラフの代わり） */
function showEmptyChartMessage(canvas) {
  canvas.style.display = "none";

  const wrap = canvas.parentElement;

  const empty = document.createElement("div");
  empty.className = "chart-empty";
  empty.textContent = "表示するデータがありません";

  wrap.appendChild(empty);
}


/* ==========================
   円グラフ用カラー生成
========================== */
function generateColors(count) {
  const baseColors = [
    '#4285F4',
    '#EA4335',
    '#FBBC05',
    '#34A853',
    '#8E24AA',
    '#00ACC1',
    '#F4511E',
    '#7CB342'
  ];
  return Array.from({ length: count }, (_, i) =>
    baseColors[i % baseColors.length]
  );
}

/* ==========================
   カレンダー遷移
========================== */
function initCalendarLinks() {
  document.querySelectorAll('.day[data-date]').forEach(day => {
    day.addEventListener('click', () => {
      const date = day.dataset.date;
      const group = day.dataset.group;
      location.href = `input.php?group_id=${group}&date=${date}`;
    });
  });
}
/* ==========================
   グループカードクリック遷移
========================== */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.group-card[data-link]').forEach(card => {
    card.addEventListener('click', e => {
      // canvas や内部要素操作を邪魔しない
      if (e.target.closest('canvas')) return;

      const link = card.dataset.link;
      if (link) {
        location.href = link;
      }
    });
  });
});

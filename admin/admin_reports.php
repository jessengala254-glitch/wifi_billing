<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Leo Konnect - Admin Reports</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

  <link rel="stylesheet" href="admin_style.css">

  <style>
    .reports-container {
      margin-left: 250px;
      padding: 30px;
      background: #f9fafb;
      min-height: 100vh;
    }

    .reports-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }

    .reports-header h1 {
      color: #333;
      font-size: 1.8rem;
      font-weight: 700;
    }

    #downloadPDF {
      background: linear-gradient(135deg, #006837, #00c853);
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s ease;
    }

    #downloadPDF:hover {
      opacity: 0.9;
      transform: scale(1.02);
    }

    .charts-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 25px;
    }

    .chart-card {
      background: #fff;
      border-radius: 16px;
      padding: 25px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
    }

    .chart-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    canvas {
      width: 100% !important;
      height: 280px !important;
    }

    @media (max-width: 768px) {
      .reports-container {
        margin-left: 0;
        padding: 20px;
      }
    }
  </style>
</head>

<body>
  <?php include 'sidebar.php'; ?>

  <div class="reports-container">
    <div class="reports-header">
      <h1>Admin Reports Dashboard</h1>
      <button id="downloadPDF">Download Report (PDF)</button>
    </div>

    <div class="charts-grid">
      <div class="chart-card">
        <h3>Revenue per Plan</h3>
        <canvas id="revenueChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Subscription Status</h3>
        <canvas id="statusChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Monthly Subscribers Trend</h3>
        <canvas id="subscribersTrendChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Monthly Revenue Trend</h3>
        <canvas id="revenueTrendChart"></canvas>
      </div>
    </div>
  </div>

 <script>
fetch('report_data.php')
  .then(async (res) => {
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`Server error: ${res.status}\n\n${text}`);
    }
    return res.json();
  })
  .then(data => {
    if (data.error) {
      console.error("Backend error:", data.error);
      alert("An error occurred while loading data.");
      return;
    }

    // Chart data
    const revenueLabels = data.revenueData.map(r => r.plan_name);
    const revenueValues = data.revenueData.map(r => r.total_revenue);

    const statusLabels = data.statusData.map(s => s.status);
    const statusValues = data.statusData.map(s => s.count);

    const monthLabels = data.monthlyData.map(m => m.month);
    const monthRevenue = data.monthlyData.map(m => m.revenue);
    const monthSubscribers = data.monthlyData.map(m => m.total_subscribers);

    // Revenue per Plan – Bar Chart
    new Chart(document.getElementById('revenueChart'), {
      type: 'bar',
      data: {
        labels: revenueLabels,
        datasets: [{
          label: 'Revenue (Ksh)',
          data: revenueValues,
          backgroundColor: revenueValues.map(v => `rgba(0,200,83,${0.5 + (v/Math.max(...revenueValues))/2})`),
          borderColor: '#00c853',
          borderWidth: 1,
          hoverBackgroundColor: '#006837'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => `Ksh ${ctx.raw.toLocaleString()}`
            }
          }
        },
        scales: {
          y: { 
            beginAtZero: true,
            ticks: {
              callback: v => 'Ksh ' + v.toLocaleString(),
              color: '#333'
            },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { ticks: { color: '#333' }, grid: { display: false } }
        }
      }
    });

    // Subscription Status – Pie Chart
    new Chart(document.getElementById('statusChart'), {
      type: 'pie',
      data: {
        labels: statusLabels,
        datasets: [{
          data: statusValues,
          backgroundColor: ['#00c853','#ff5252','#ffc107'],
          borderColor: '#fff',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom', labels: { color: '#333' } },
          tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw} users` } }
        }
      }
    });

    // Monthly Subscribers Trend – Line Chart
    const subsCtx = document.getElementById('subscribersTrendChart').getContext('2d');
    new Chart(subsCtx, {
      type: 'line',
      data: {
        labels: monthLabels,
        datasets: [{
          label: 'Subscribers',
          data: monthSubscribers,
          borderColor: '#006837',
          backgroundColor: 'rgba(0,104,55,0.15)',
          borderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, position: 'top', labels: { color: '#333' } } },
        scales: {
          y: { 
            beginAtZero: true,
            ticks: { color: '#333', precision:0 },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { ticks: { color: '#333' }, grid: { color: 'rgba(0,0,0,0.05)' } }
        }
      }
    });

    // Monthly Revenue Trend – Line Chart
    const revCtx = document.getElementById('revenueTrendChart').getContext('2d');
    new Chart(revCtx, {
      type: 'line',
      data: {
        labels: monthLabels,
        datasets: [{
          label: 'Revenue (Ksh)',
          data: monthRevenue,
          borderColor: '#00c853',
          backgroundColor: 'rgba(0,200,83,0.15)',
          borderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: true, position: 'top', labels: { color: '#333' } },
          tooltip: { callbacks: { label: ctx => `Ksh ${ctx.raw.toLocaleString()}` } }
        },
        scales: {
          y: { 
            beginAtZero: true,
            ticks: { callback: v => 'Ksh ' + v.toLocaleString(), color: '#333' },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { ticks: { color: '#333' }, grid: { color: 'rgba(0,0,0,0.05)' } }
        }
      }
    });

    // PDF Download
    document.getElementById('downloadPDF').addEventListener('click', () => {
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF();

      pdf.setFontSize(16);
      pdf.text('Leo Konnect - Admin Report', 20, 20);
      pdf.setFontSize(11);
      pdf.text(`Generated on: ${new Date().toLocaleString()}`, 20, 30);

      pdf.setFontSize(13);
      pdf.text('Revenue per Plan', 20, 45);
      pdf.line(20, 47, 190, 47);

      let y = 55;
      pdf.setFontSize(11);
      data.revenueData.forEach(r => {
        pdf.text(`${r.plan_name}: ${r.total_purchases} purchases — Ksh ${r.total_revenue}`, 25, y);
        y += 8;
      });

      pdf.setFontSize(13);
      pdf.text('Monthly Revenue & Subscribers', 20, y + 10);
      pdf.line(20, y + 12, 190, y + 12);
      y += 20;

      data.monthlyData.forEach(m => {
        pdf.text(`${m.month}: Ksh ${m.revenue} — ${m.total_subscribers} subscribers`, 25, y);
        y += 8;
      });

      pdf.save('LeoKonnect_Report.pdf');
    });

  })
  .catch(err => {
    console.error(err);
    alert("Error fetching report data. Check console for details.");
  });
</script>

</body>
</html>

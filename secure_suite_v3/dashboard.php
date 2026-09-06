<?php
// ====================================================================
// SECURE V3 (INTEGRATED METRIC NODE) - dashboard.php
// ====================================================================

require_once 'db.php';
// If your system uses check_session_guard(), we keep it safe here:
if (function_exists('check_session_guard')) {
    check_session_guard();
}

// 1. DATA AGGREGATION FROM WORK_TRACKER
$works = $pdo->query("SELECT * FROM work_tracker")->fetchAll();

$stat_total_jobs = count($works);
$stat_completed = 0; 
$stat_pending = 0;
$stat_total_billed = 0.00; 
$stat_total_paid = 0.00;

$financial_months = ['04', '05', '06', '07', '08', '09', '10', '11', '12', '01', '02', '03'];
$month_labels = ['04'=>'Apr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec','01'=>'Jan','02'=>'Feb','03'=>'Mar'];
$monthly_earnings = array_fill_keys($financial_months, 0.00);

foreach ($works as $w) {
    // Column fallback match matching your exact schema structure
    $bill = isset($w['bill_amount']) ? floatval($w['bill_amount']) : 0.00;
    $paid = isset($w['paid_amount']) ? floatval($w['paid_amount']) : 0.00;
    
    $stat_total_billed += $bill;
    $stat_total_paid += $paid;
    
    if (strtolower($w['status']) === 'completed') {
        $stat_completed++;
    } else {
        $stat_pending++;
    }
    
    // Parse timestamp based on your 'received_date' column
    if (!empty($w['received_date'])) {
        $m = date('m', strtotime($w['received_date']));
        if (isset($monthly_earnings[$m])) {
            $monthly_earnings[$m] += $bill;
        }
    }
}
$stat_outstanding_arrears = max(0, $stat_total_billed - $stat_total_paid);

// 2. LEADERBOARD AGGREGATION (Using 'ta_name')
$ta_leaderboard = $pdo->query("
    SELECT ta_name, COUNT(id) as total_assigned, SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as total_completed 
    FROM work_tracker GROUP BY ta_name ORDER BY total_assigned DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SECURE V3 - System Dashboard</title>
    <!-- Tailwind CSS Script Engine -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- ChartJS Graphing Engine for the Bar graph -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-900 text-slate-100 font-sans antialiased min-h-screen">

    <!-- CRITICAL PENDING PAYMENTS ALERT BANNER -->
    <?php if ($stat_pending > 0): ?>
    <div class="bg-red-950/40 border border-red-900/60 text-red-200 px-6 py-4 mx-6 mt-6 rounded-xl flex items-center justify-between shadow-lg animate-pulse">
        <div class="flex items-center gap-4">
            <div class="p-2 bg-red-900/50 rounded-lg text-xl">⚠️</div>
            <div>
                <h5 class="font-bold text-red-400 tracking-wide uppercase text-xs">System Balance Warning</h5>
                <p class="text-sm mt-0.5">
                    There are currently <span class="underline font-black text-white"><?php echo $stat_pending; ?></span> operations pending settlement. Total uncollected outstanding balance: <span class="font-bold text-red-400 text-base">₹<?php echo number_format($stat_outstanding_arrears, 2); ?></span>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="p-6 space-y-6">
        
        <!-- DASHBOARD METRIC SUMMARY CARDS ROW -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Card 1 -->
            <div class="bg-slate-800/60 backdrop-blur-sm border border-slate-700/50 rounded-xl p-5 shadow-md hover:border-blue-500/50 transition-all duration-300">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Ingested Jobs Operational Volume</p>
                <h3 class="text-3xl font-black text-blue-400 mt-2"><?php echo $stat_total_jobs; ?> Records</h3>
            </div>
            
            <!-- Card 2 -->
            <div class="bg-slate-800/60 backdrop-blur-sm border border-slate-700/50 rounded-xl p-5 shadow-md hover:border-green-500/50 transition-all duration-300">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Settled Closed Accounts Count</p>
                <h3 class="text-3xl font-black text-green-400 mt-2"><?php echo $stat_completed; ?> Jobs</h3>
            </div>

            <!-- Card 3 -->
            <div class="bg-slate-800/60 backdrop-blur-sm border border-slate-700/50 rounded-xl p-5 shadow-md hover:border-amber-500/50 transition-all duration-300">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Outstanding Processing Queue</p>
                <h3 class="text-3xl font-black text-amber-400 mt-2"><?php echo $stat_pending; ?> Pending</h3>
            </div>

            <!-- Card 4 -->
            <div class="bg-slate-800/60 backdrop-blur-sm border border-slate-700/50 rounded-xl p-5 shadow-md hover:border-red-500/50 transition-all duration-300">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Gross Owed Network Balance</p>
                <h3 class="text-3xl font-black text-red-400 mt-2">₹<?php echo number_format($stat_outstanding_arrears, 2); ?></h3>
            </div>
        </div>

        <!-- MAIN LAYOUT GRAPH & LEADERBOARD GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- CHART CONTAINER BLOCK -->
            <div class="lg:col-span-2 bg-slate-800/50 border border-slate-700/60 p-6 rounded-xl shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-xs font-bold uppercase text-slate-400 tracking-widest">Financial Operations Billings Chart (FY Breakdown)</h4>
                    <span class="text-xs font-mono text-slate-500">Render Node: Active</span>
                </div>
                <div class="relative h-80">
                    <canvas id="financialChartCanvas"></canvas>
                </div>
            </div>

            <!-- OPERATORS LEADERBOARD TABLE -->
            <div class="bg-slate-800/50 border border-slate-700/60 p-6 rounded-xl shadow-lg flex flex-col">
                <h4 class="text-xs font-bold uppercase text-slate-400 tracking-widest mb-4">Top Active Profiling Operations</h4>
                <div class="overflow-x-auto rounded-lg border border-slate-700/40 flex-grow">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 font-mono">
                            <tr>
                                <th class="p-3">Staff Operator Name</th>
                                <th class="p-3 text-center">Assigned</th>
                                <th class="p-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            <?php foreach ($ta_leaderboard as $ta): ?>
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="p-3 font-medium text-slate-200"><?php echo htmlspecialchars($ta['ta_name']); ?></td>
                                <td class="p-3 text-center text-blue-400 font-bold font-mono"><?php echo $ta['total_assigned']; ?></td>
                                <td class="p-3 text-right">
                                    <span class="bg-green-500/10 text-green-400 border border-green-500/20 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                        <?php echo $ta['total_completed']; ?> Closed
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- JAVASCRIPT GRAPH INTEGRATION DATA INJECTION -->
    <script>
        const ctx = document.getElementById('financialChartCanvas').getContext('2d');
        
        // Mapped values directly corresponding to your array loop sequences
        const monthsLabels = [
            <?php foreach($financial_months as $m_code) { echo '"' . $month_labels[$m_code] . '",'; } ?>
        ];
        const billingDataset = [
            <?php foreach($financial_months as $m_code) { echo $monthly_earnings[$m_code] . ','; } ?>
        ];

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthsLabels,
                datasets: [{
                    label: 'Calculated Monthly Billing Total (₹)',
                    data: billingDataset,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)', 
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#10b981' // Highlights emerald green on chart element hover interaction
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: { color: '#94a3b8', font: { weight: '600', family: 'sans-serif' } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' Billings: ₹' + context.raw.toLocaleString('en-IN');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(51, 65, 85, 0.3)' },
                        ticks: { color: '#94a3b8' }
                    },
                    y: {
                        grid: { color: 'rgba(51, 65, 85, 0.3)' },
                        ticks: { 
                            color: '#94a3b8',
                            callback: function(value) { return '₹' + value.toLocaleString('en-IN'); }
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
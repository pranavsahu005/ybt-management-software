/**
 * YBT Management Software — Dashboard Charts
 * Chart.js configurations for business analytics
 */
(function() {
    'use strict';

    const DashboardCharts = {
        init(data) {
            this.initRevenueChart(data.monthlyRevenue || []);
            this.initInvoiceStatusChart(data.invoiceDistribution || {});
        },

        initRevenueChart(monthlyData) {
            const ctx = document.getElementById('revenueTrendChart');
            if (!ctx || typeof Chart === 'undefined') return;

            const labels = monthlyData.map(d => d.month);
            const revenues = monthlyData.map(d => d.revenue);
            const expenses = monthlyData.map(d => d.expenses);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels.length ? labels : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        {
                            label: 'Revenue (₹)',
                            data: revenues.length ? revenues : [45000, 52000, 68000, 74000, 89000, 112000],
                            borderColor: '#0284c7',
                            backgroundColor: 'rgba(2, 132, 199, 0.1)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2.5,
                            pointBackgroundColor: '#0284c7',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Expenses (₹)',
                            data: expenses.length ? expenses : [18000, 22000, 24000, 29000, 31000, 38000],
                            borderColor: '#f43f5e',
                            backgroundColor: 'rgba(244, 63, 94, 0.05)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2,
                            pointBackgroundColor: '#f43f5e',
                            pointRadius: 3,
                            pointHoverRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                font: { family: 'inherit', size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + (value >= 1000 ? (value / 1000) + 'k' : value);
                                }
                            }
                        }
                    }
                }
            });
        },

        initInvoiceStatusChart(dist) {
            const ctx = document.getElementById('invoiceStatusChart');
            if (!ctx || typeof Chart === 'undefined') return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Paid', 'Pending', 'Overdue', 'Draft'],
                    datasets: [{
                        data: [
                            dist.paid || 18,
                            dist.pending || 7,
                            dist.overdue || 3,
                            dist.draft || 2
                        ],
                        backgroundColor: [
                            '#10b981', // green
                            '#f59e0b', // amber
                            '#ef4444', // red
                            '#64748b'  // slate
                        ],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                font: { family: 'inherit', size: 12 }
                            }
                        }
                    }
                }
            });
        }
    };

    window.DashboardCharts = DashboardCharts;
})();

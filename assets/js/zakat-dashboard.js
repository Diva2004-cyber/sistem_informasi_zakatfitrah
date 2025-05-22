/**
 * ZakatFitrah Dashboard Charts
 * This file handles all Google Charts visualizations for the admin dashboard
 */

// Load the Google Charts API
google.charts.load('current', {'packages':['corechart', 'bar', 'calendar']});
google.charts.setOnLoadCallback(initCharts);

// Initialize all charts when Google Charts is loaded
function initCharts() {
    drawMuzakkiMustahikChart();
    drawZakatTypeChart();
    drawDistributionStatusChart();
    drawMonthlyCollectionChart();
    drawWeeklyActivityChart();
    drawCategoryDistributionChart();
}

// Chart 1: Muzakki vs Mustahik Count
function drawMuzakkiMustahikChart() {
    // Get the chart container
    const chartElement = document.getElementById('muzakki-mustahik-chart');
    if (!chartElement) return;
    
    // Get data from data attributes
    const muzakkiCount = parseInt(chartElement.getAttribute('data-muzakki') || 0);
    const mustahikCount = parseInt(chartElement.getAttribute('data-mustahik') || 0);
    
    const data = google.visualization.arrayToDataTable([
        ['Category', 'Count'],
        ['Muzakki', muzakkiCount],
        ['Mustahik', mustahikCount]
    ]);
    
    const options = {
        title: 'Perbandingan Muzakki dan Mustahik',
        height: 300,
        legend: { position: 'bottom' },
        colors: ['#3498db', '#27ae60'],
        chartArea: { width: '80%', height: '70%' },
        animation: {
            startup: true,
            duration: 1000,
            easing: 'out'
        }
    };
    
    const chart = new google.visualization.PieChart(chartElement);
    chart.draw(data, options);
    
    // Redraw chart on window resize
    window.addEventListener('resize', () => {
        chart.draw(data, options);
    });
}

// Chart 2: Zakat Type Distribution (Beras vs Uang)
function drawZakatTypeChart() {
    const chartElement = document.getElementById('zakat-type-chart');
    if (!chartElement) return;
    
    // Get data from data attributes
    const berasAmount = parseFloat(chartElement.getAttribute('data-beras') || 0);
    const uangAmount = parseFloat(chartElement.getAttribute('data-uang') || 0);
    
    const data = google.visualization.arrayToDataTable([
        ['Jenis', 'Jumlah'],
        ['Beras (Kg)', berasAmount],
        ['Uang (Rp)', uangAmount / 1000000] // Convert to millions for better display
    ]);
    
    const options = {
        title: 'Distribusi Jenis Zakat',
        height: 300,
        legend: { position: 'bottom' },
        colors: ['#f39c12', '#9b59b6'],
        chartArea: { width: '80%', height: '70%' },
        animation: {
            startup: true,
            duration: 1000,
            easing: 'out'
        }
    };
    
    const chart = new google.visualization.ColumnChart(chartElement);
    chart.draw(data, options);
    
    // Redraw chart on window resize
    window.addEventListener('resize', () => {
        chart.draw(data, options);
    });
}

// Chart 3: Distribution Status
function drawDistributionStatusChart() {
    const chartElement = document.getElementById('distribution-status-chart');
    if (!chartElement) return;
    
    // Get data from data attributes
    const pendingCount = parseInt(chartElement.getAttribute('data-pending') || 0);
    const completedCount = parseInt(chartElement.getAttribute('data-completed') || 0);
    const cancelledCount = parseInt(chartElement.getAttribute('data-cancelled') || 0);
    
    const data = google.visualization.arrayToDataTable([
        ['Status', 'Count'],
        ['Tertunda', pendingCount],
        ['Selesai', completedCount],
        ['Dibatalkan', cancelledCount]
    ]);
    
    const options = {
        title: 'Status Distribusi',
        height: 300,
        pieHole: 0.4,
        legend: { position: 'bottom' },
        colors: ['#f39c12', '#27ae60', '#e74c3c'],
        chartArea: { width: '80%', height: '70%' },
        animation: {
            startup: true,
            duration: 1000,
            easing: 'out'
        }
    };
    
    const chart = new google.visualization.PieChart(chartElement);
    chart.draw(data, options);
    
    // Redraw chart on window resize
    window.addEventListener('resize', () => {
        chart.draw(data, options);
    });
}

// Chart 4: Monthly Collection Chart
function drawMonthlyCollectionChart() {
    const chartElement = document.getElementById('monthly-collection-chart');
    if (!chartElement) return;
    
    // Get monthly collection data from data attribute
    // Format should be: "[['Month', 'Beras (Kg)', 'Uang (Rp)'], ['Jan', 100, 5000000], ...]"
    try {
        const monthlyData = JSON.parse(chartElement.getAttribute('data-monthly') || '[]');
        if (!monthlyData.length) return;
        
        const data = google.visualization.arrayToDataTable(monthlyData);
        
        const options = {
            title: 'Pengumpulan Zakat Bulanan',
            height: 350,
            legend: { position: 'top' },
            colors: ['#f39c12', '#3498db'],
            chartArea: { width: '85%', height: '70%' },
            hAxis: {
                title: 'Bulan'
            },
            vAxis: {
                title: 'Jumlah'
            },
            seriesType: 'bars',
            series: {1: {type: 'line', targetAxisIndex: 1}},
            animation: {
                startup: true,
                duration: 1000,
                easing: 'out'
            }
        };
        
        const chart = new google.visualization.ComboChart(chartElement);
        chart.draw(data, options);
        
        // Redraw chart on window resize
        window.addEventListener('resize', () => {
            chart.draw(data, options);
        });
    } catch (error) {
        console.error('Error parsing monthly data:', error);
    }
}

// Chart 5: Weekly Activity Chart
function drawWeeklyActivityChart() {
    const chartElement = document.getElementById('weekly-activity-chart');
    if (!chartElement) return;
    
    // Get weekly activity data from data attribute
    // Format should be: "[['Day', 'Activities'], ['Mon', 5], ...]"
    try {
        const weeklyData = JSON.parse(chartElement.getAttribute('data-weekly') || '[]');
        if (!weeklyData.length) return;
        
        const data = google.visualization.arrayToDataTable(weeklyData);
        
        const options = {
            title: 'Aktivitas Mingguan',
            height: 350,
            legend: { position: 'none' },
            colors: ['#3498db'],
            chartArea: { width: '85%', height: '70%' },
            hAxis: {
                title: 'Hari'
            },
            vAxis: {
                title: 'Jumlah Aktivitas',
                minValue: 0
            },
            animation: {
                startup: true,
                duration: 1000,
                easing: 'out'
            }
        };
        
        const chart = new google.visualization.ColumnChart(chartElement);
        chart.draw(data, options);
        
        // Redraw chart on window resize
        window.addEventListener('resize', () => {
            chart.draw(data, options);
        });
    } catch (error) {
        console.error('Error parsing weekly data:', error);
    }
}

// Chart 6: Category Distribution Chart
function drawCategoryDistributionChart() {
    const chartElement = document.getElementById('category-distribution-chart');
    if (!chartElement) return;
    
    // Get category distribution data from data attribute
    // Format should be: "[['Category', 'Amount'], ['Fakir', 25], ...]"
    try {
        const categoryData = JSON.parse(chartElement.getAttribute('data-categories') || '[]');
        if (!categoryData.length) return;
        
        const data = google.visualization.arrayToDataTable(categoryData);
        
        const options = {
            title: 'Distribusi Berdasarkan Kategori Mustahik',
            height: 350,
            legend: { position: 'right' },
            pieSliceText: 'percentage',
            chartArea: { width: '85%', height: '85%' },
            colors: ['#e74c3c', '#f39c12', '#27ae60', '#3498db', '#9b59b6', '#1abc9c', '#34495e', '#95a5a6'],
            animation: {
                startup: true,
                duration: 1000,
                easing: 'out'
            }
        };
        
        const chart = new google.visualization.PieChart(chartElement);
        chart.draw(data, options);
        
        // Redraw chart on window resize
        window.addEventListener('resize', () => {
            chart.draw(data, options);
        });
    } catch (error) {
        console.error('Error parsing category data:', error);
    }
}

// Function to update charts with new data (can be called after AJAX updates)
function updateCharts(chartData) {
    if (chartData.muzakkiMustahik) {
        const chartElement = document.getElementById('muzakki-mustahik-chart');
        if (chartElement) {
            chartElement.setAttribute('data-muzakki', chartData.muzakkiMustahik.muzakki);
            chartElement.setAttribute('data-mustahik', chartData.muzakkiMustahik.mustahik);
            drawMuzakkiMustahikChart();
        }
    }
    
    if (chartData.zakatType) {
        const chartElement = document.getElementById('zakat-type-chart');
        if (chartElement) {
            chartElement.setAttribute('data-beras', chartData.zakatType.beras);
            chartElement.setAttribute('data-uang', chartData.zakatType.uang);
            drawZakatTypeChart();
        }
    }
    
    if (chartData.distributionStatus) {
        const chartElement = document.getElementById('distribution-status-chart');
        if (chartElement) {
            chartElement.setAttribute('data-pending', chartData.distributionStatus.pending);
            chartElement.setAttribute('data-completed', chartData.distributionStatus.completed);
            chartElement.setAttribute('data-cancelled', chartData.distributionStatus.cancelled);
            drawDistributionStatusChart();
        }
    }
    
    if (chartData.monthlyCollection) {
        const chartElement = document.getElementById('monthly-collection-chart');
        if (chartElement) {
            chartElement.setAttribute('data-monthly', JSON.stringify(chartData.monthlyCollection));
            drawMonthlyCollectionChart();
        }
    }
    
    if (chartData.weeklyActivity) {
        const chartElement = document.getElementById('weekly-activity-chart');
        if (chartElement) {
            chartElement.setAttribute('data-weekly', JSON.stringify(chartData.weeklyActivity));
            drawWeeklyActivityChart();
        }
    }
    
    if (chartData.categoryDistribution) {
        const chartElement = document.getElementById('category-distribution-chart');
        if (chartElement) {
            chartElement.setAttribute('data-categories', JSON.stringify(chartData.categoryDistribution));
            drawCategoryDistributionChart();
        }
    }
}

// Export functions for use in other scripts
window.zakatDashboard = {
    updateCharts: updateCharts
}; 
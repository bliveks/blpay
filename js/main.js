// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
});

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('az-AZ', {
        style: 'currency',
        currency: 'AZN',
        minimumFractionDigits: 2
    }).format(amount);
}

// Format date
function formatDate(date) {
    return new Intl.DateTimeFormat('az-AZ', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).format(new Date(date));
}

// Delete confirmation
function confirmDelete(event, type) {
    event.preventDefault();
    const form = event.target;
    const message = type === 'kateqoriya' ? 'Bu kateqoriyanı silmək istədiyinizə əminsiniz?' : 
                   type === 'gəlir' ? 'Bu gəliri silmək istədiyinizə əminsiniz?' :
                   'Bu xərci silmək istədiyinizə əminsiniz?';
    
    if (confirm(message)) {
        form.submit();
    }
    return false;
}

// Dynamic category selection
function updateCategories(selectElement, type) {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `get_categories.php?type=${type}`, true);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            const categories = JSON.parse(xhr.responseText);
            selectElement.innerHTML = '<option value="">Kateqoriya seçin</option>';
            
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.ad;
                selectElement.appendChild(option);
            });
        }
    };
    
    xhr.send();
}

// Chart initialization
function initializeCharts(data) {
    if (typeof Chart === 'undefined') return;
    
    // Monthly summary chart
    const monthlySummaryCtx = document.getElementById('monthlySummaryChart');
    if (monthlySummaryCtx) {
        new Chart(monthlySummaryCtx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Gəlirlər',
                        data: data.income,
                        backgroundColor: 'rgba(25, 135, 84, 0.5)',
                        borderColor: 'rgb(25, 135, 84)',
                        borderWidth: 1
                    },
                    {
                        label: 'Xərclər',
                        data: data.expense,
                        backgroundColor: 'rgba(220, 53, 69, 0.5)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatCurrency(context.raw);
                            }
                        }
                    }
                }
            }
        });
    }
}

// Print report
function printReport() {
    window.print();
}

// Export to Excel
function exportToExcel(tableId, fileName) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, {sheet: "Sheet JS"});
    XLSX.writeFile(wb, fileName);
}

// Show/hide password
function togglePassword(inputId, buttonId) {
    const input = document.getElementById(inputId);
    const button = document.getElementById(buttonId);
    
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = 'Gizlət';
    } else {
        input.type = 'password';
        button.textContent = 'Göstər';
    }
}

// Responsive sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
});

// Savings Goals Functions

// Delete goal confirmation
function confirmDeleteGoal(event) {
    event.preventDefault();
    const form = event.target;
    
    if (confirm('Bu hədəfi silmək istədiyinizə əminsiniz?')) {
        form.submit();
    }
    return false;
}

// Update goal progress bar
function updateGoalProgress() {
    const progressBars = document.querySelectorAll('.goal-progress-bar');
    
    progressBars.forEach(progressBar => {
        const progress = parseFloat(progressBar.getAttribute('data-progress'));
        const maxValue = 100;
        const percentage = (progress / maxValue) * 100;
        
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
        
        // Change color based on progress
        if (percentage >= 90) {
            progressBar.classList.add('bg-success');
        } else if (percentage >= 50) {
            progressBar.classList.add('bg-info');
        } else if (percentage >= 25) {
            progressBar.classList.add('bg-warning');
        } else {
            progressBar.classList.add('bg-danger');
        }
    });
}

// Initialize goal chart
function initializeGoalChart(chartId, currentAmount, targetAmount) {
    if (typeof Chart === 'undefined') return;
    
    const ctx = document.getElementById(chartId);
    if (!ctx) return;
    
    const remainingAmount = Math.max(0, targetAmount - currentAmount);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Toplanan', 'Qalan'],
            datasets: [{
                data: [currentAmount, remainingAmount],
                backgroundColor: [
                    'rgba(13, 110, 253, 0.8)',
                    'rgba(222, 226, 230, 0.8)'
                ],
                borderColor: [
                    'rgba(13, 110, 253, 1)',
                    'rgba(222, 226, 230, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            return label + ': ' + formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
}

// Add transaction to goal
function showAddTransactionModal(goalId, goalName) {
    const modal = document.getElementById('transactionModal');
    if (!modal) return;
    
    const modalTitle = modal.querySelector('.modal-title');
    const goalIdField = document.getElementById('transaction_goal_id');
    
    modalTitle.textContent = goalName + ' - Yeni Əməliyyat';
    goalIdField.value = goalId;
    
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}

// Initialize datepicker for goal forms
function initializeDatepickers() {
    const dateInputs = document.querySelectorAll('.datepicker');
    
    dateInputs.forEach(input => {
        if (typeof flatpickr !== 'undefined') {
            flatpickr(input, {
                dateFormat: "Y-m-d",
                locale: "az",
                altInput: true,
                altFormat: "d.m.Y",
                minDate: "today"
            });
        }
    });
}

// Calculate and display time remaining for goals
function updateGoalTimeRemaining() {
    const timeElements = document.querySelectorAll('.goal-time-remaining');
    
    timeElements.forEach(element => {
        const targetDate = new Date(element.getAttribute('data-target-date'));
        const today = new Date();
        
        // Calculate difference in days
        const diffTime = targetDate.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        let timeText = '';
        
        if (diffDays < 0) {
            timeText = 'Müddət bitib';
            element.classList.add('text-danger');
        } else if (diffDays === 0) {
            timeText = 'Bu gün';
        } else if (diffDays === 1) {
            timeText = 'Sabah';
        } else if (diffDays < 30) {
            timeText = diffDays + ' gün';
        } else if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            timeText = months + ' ay';
        } else {
            const years = Math.floor(diffDays / 365);
            const remainingMonths = Math.floor((diffDays % 365) / 30);
            
            timeText = years + ' il';
            if (remainingMonths > 0) {
                timeText += ' ' + remainingMonths + ' ay';
            }
        }
        
        element.textContent = timeText;
    });
}

// Initialize all goal-related functions
document.addEventListener('DOMContentLoaded', function() {
    // Only run these functions on the goals page
    if (document.getElementById('goals-container')) {
        updateGoalProgress();
        initializeDatepickers();
        updateGoalTimeRemaining();
        
        // Initialize charts for each goal
        const goalCharts = document.querySelectorAll('[id^="goalChart-"]');
        goalCharts.forEach(chart => {
            const goalId = chart.id.split('-')[1];
            const currentAmount = parseFloat(chart.getAttribute('data-current-amount'));
            const targetAmount = parseFloat(chart.getAttribute('data-target-amount'));
            
            initializeGoalChart(chart.id, currentAmount, targetAmount);
        });
    }
});
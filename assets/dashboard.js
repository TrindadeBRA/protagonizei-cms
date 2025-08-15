// Dashboard JavaScript para Protagonizei CMS

function dashboardApp() {
    return {
        loading: false,
        stats: {
            total_orders: 0,
            total_revenue: 0,
            total_coupons_used: 0,
            average_order_value: 0,
            orders_by_status: [],
            orders_by_month: [],
            top_coupons: [],
            recent_orders: [],
            conversion_funnel: []
        },
        filters: {
            start_date: '',
            end_date: '',
            status: ''
        },
        charts: {
            status: null,
            revenue: null
        },

        init() {
            console.log('Dashboard inicializando...');
            console.log('Chart.js disponível:', typeof Chart !== 'undefined');
            console.log('Elementos canvas existem:', 
                !!document.getElementById('statusChart'), 
                !!document.getElementById('revenueChart')
            );
            
            // Aguardar um momento para garantir que tudo esteja carregado
            setTimeout(() => {
                this.loadData();
            }, 500);
        },

        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.filters.start_date) params.append('start_date', this.filters.start_date);
                if (this.filters.end_date) params.append('end_date', this.filters.end_date);
                if (this.filters.status) params.append('status', this.filters.status);

                const response = await fetch(`/wp-json/protagonizei/v1/dashboard/stats?${params}`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.wpApiSettings?.nonce || ''
                    }
                });
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `Erro HTTP: ${response.status}`);
                }
                
                this.stats = await response.json();
                
                // Aguardar um pouco para garantir que elementos DOM existam
                setTimeout(() => {
                    this.updateCharts();
                }, 100);
                
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                
                let errorMessage = 'Erro ao carregar dados do dashboard';
                if (error.message.includes('401') || error.message.includes('não tem permissão')) {
                    errorMessage = 'Você precisa estar logado para acessar o dashboard. Faça login e tente novamente.';
                } else if (error.message.includes('403')) {
                    errorMessage = 'Você não tem permissão para acessar estes dados.';
                } else if (error.message.includes('404')) {
                    errorMessage = 'Endpoint da API não encontrado. Verifique a configuração.';
                }
                
                alert(errorMessage);
            } finally {
                this.loading = false;
            }
        },

        async refreshData() {
            await this.loadData();
        },

        async testEndpoint() {
            try {
                console.log('Testando endpoint...');
                console.log('URL:', '/wp-json/protagonizei/v1/dashboard/stats');
                console.log('Nonce:', window.wpApiSettings?.nonce);
                console.log('User logged in:', document.cookie.includes('wordpress_logged_in'));
                
                const response = await fetch('/wp-json/protagonizei/v1/dashboard/stats', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.wpApiSettings?.nonce || ''
                    }
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                const data = await response.json();
                console.log('Response data:', data);
                
                if (response.ok) {
                    alert('✅ API funcionando! Dados recebidos com sucesso. Verifique o console para detalhes.');
                } else {
                    alert('❌ Erro na API: ' + (data.message || 'Erro desconhecido'));
                }
                
            } catch (error) {
                console.error('Erro no teste:', error);
                alert('❌ Erro ao testar API: ' + error.message);
            }
        },

        async applyFilters() {
            await this.loadData();
        },

        clearFilters() {
            this.filters = {
                start_date: '',
                end_date: '',
                status: ''
            };
            this.loadData();
        },

        updateCharts() {
            // Verificar se Chart.js está disponível antes de tentar criar gráficos
            if (typeof Chart === 'undefined') {
                console.log('Chart.js ainda não está disponível, aguardando...');
                setTimeout(() => {
                    this.updateCharts();
                }, 500);
                return;
            }
            
            // Aguardar um pouco mais para garantir que os elementos canvas existam
            setTimeout(() => {
                this.updateStatusChart();
                this.updateRevenueChart();
            }, 100);
        },

        updateStatusChart() {
            const ctx = document.getElementById('statusChart');
            if (!ctx || !this.stats || !this.stats.orders_by_status) {
                console.log('Status chart: elemento não encontrado ou dados não disponíveis');
                return;
            }

            // Verificar se Chart.js está disponível
            if (typeof Chart === 'undefined') {
                console.error('Chart.js não está carregado');
                return;
            }

            // Destruir gráfico anterior se existir
            if (this.charts.status) {
                try {
                    this.charts.status.destroy();
                } catch (e) {
                    console.log('Erro ao destruir gráfico anterior:', e);
                }
                this.charts.status = null;
            }

            const data = this.stats.orders_by_status.filter(item => item && item.count > 0);
            
            if (data.length === 0) {
                console.log('Nenhum dado para exibir no gráfico de status');
                return;
            }

            try {
                this.charts.status = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(item => item.label),
                        datasets: [{
                            data: data.map(item => item.count),
                            backgroundColor: [
                                '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
                                '#8B5CF6', '#EC4899', '#6B7280', '#14B8A6',
                                '#F97316', '#84CC16'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Erro ao criar gráfico de status:', error);
            }
        },

        updateRevenueChart() {
            const ctx = document.getElementById('revenueChart');
            if (!ctx || !this.stats || !this.stats.orders_by_month) {
                console.log('Revenue chart: elemento não encontrado ou dados não disponíveis');
                return;
            }

            // Verificar se Chart.js está disponível
            if (typeof Chart === 'undefined') {
                console.error('Chart.js não está carregado');
                return;
            }

            // Destruir gráfico anterior se existir
            if (this.charts.revenue) {
                try {
                    this.charts.revenue.destroy();
                } catch (e) {
                    console.log('Erro ao destruir gráfico anterior:', e);
                }
                this.charts.revenue = null;
            }

            if (this.stats.orders_by_month.length === 0) {
                console.log('Nenhum dado para exibir no gráfico de receita');
                return;
            }

            try {
                this.charts.revenue = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.stats.orders_by_month.map(item => item.label),
                        datasets: [{
                            label: 'Receita (R$)',
                            data: this.stats.orders_by_month.map(item => item.revenue),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Erro ao criar gráfico de receita:', error);
            }
        },

        formatCurrency(value) {
            return 'R$ ' + (parseFloat(value) || 0).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        },

        getStatusBadgeClass(status) {
            const classes = {
                'created': 'bg-gray-100 text-gray-800',
                'awaiting_payment': 'bg-yellow-100 text-yellow-800',
                'paid': 'bg-green-100 text-green-800',
                'thanked': 'bg-blue-100 text-blue-800',
                'completed': 'bg-green-100 text-green-800',
                'error': 'bg-red-100 text-red-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        calculateFunnelPercentage(count, index) {
            if (!this.stats || !this.stats.conversion_funnel || this.stats.conversion_funnel.length === 0) {
                return 0;
            }
            
            try {
                const validStages = this.stats.conversion_funnel.filter(stage => stage && typeof stage.count === 'number');
                if (validStages.length === 0) return 0;
                
                const maxCount = Math.max(...validStages.map(stage => stage.count));
                return maxCount > 0 ? (count / maxCount) * 100 : 0;
            } catch (e) {
                console.error('Erro ao calcular porcentagem do funil:', e);
                return 0;
            }
        }
    }
}

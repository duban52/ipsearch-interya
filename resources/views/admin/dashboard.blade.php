@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-xl-8 col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="h3 mb-0">Dashboard</h2>
                    <div class="text-muted small">
                        Último acceso: {{ $lastLogin?->diffForHumans() ?? 'Primer acceso' }}
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- KPI: Routers -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-white bg-primary mb-3" style="height: 100%;">
                            <div class="card-body">
                                <div class="fs-2 fw-bold">{{ $kpis['routers_total'] }}</div>
                                <div class="small">Routers registrados</div>
                            </div>
                        </div>
                    </div>
                    <!-- KPI: Users -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-white bg-success mb-3" style="height: 100%;">
                            <div class="card-body">
                                <div class="fs-2 fw-bold">{{ $kpis['users_active'] }}</div>
                                <div class="small">Usuarios activos</div>
                            </div>
                        </div>
                    </div>
                    <!-- KPI: Searches Today -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-white bg-info mb-3" style="height: 100%;">
                            <div class="card-body">
                                <div class="fs-2 fw-bold">{{ $kpis['searches_today'] }}</div>
                                <div class="small">Consultas hoy</div>
                            </div>
                        </div>
                    </div>
                    <!-- KPI: Success -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-white bg-warning text-dark mb-3" style="height: 100%;">
                            <div class="card-body">
                                <div class="fs-2 fw-bold">{{ $kpis['searches_success'] }}</div>
                                <div class="small">Exitosas</div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-3">
                <div class="row">
                    <!-- Chart placeholder -->
                    <div class="col-12">
                        <canvas id="dailySearchesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('dailySearchesChart'), {
        type: 'line',
        data: {
            labels: @json($dailySeries['labels']),
            datasets: [{
                label: 'Consultas',
                data: @json($dailySeries['values']),
                borderColor: '#0071BC',
                backgroundColor: 'rgba(0,113,188,.1)',
                tension: 0.2,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { mode: 'index' }
            }
        }
    });
</script>
@endpush
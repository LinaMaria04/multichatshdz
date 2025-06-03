<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Aplicar Filtros
        </x-filament::button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
        <x-filament::card>
            <div class="text-center">
                <h2 class="text-lg font-bold">Total de Mensajes</h2>
                <p class="text-3xl font-bold mt-2">{{ number_format($this->generalStats['total_messages']) }}</p>
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-center">
                <h2 class="text-lg font-bold">Usuarios Únicos</h2>
                <p class="text-3xl font-bold mt-2">{{ number_format($this->generalStats['unique_users']) }}</p>
                <p class="text-sm text-gray-500 mt-1">En el período seleccionado</p>
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-center">
                <h2 class="text-lg font-bold">Agentes Activos</h2>
                <p class="text-3xl font-bold mt-2">{{ number_format($this->generalStats['active_agents']) }}</p>
            </div>
        </x-filament::card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8">
        <x-filament::card>
            <h2 class="text-lg font-bold mb-4">Top 10 Usuarios (por IP)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left p-2">IP</th>
                            <th class="text-right p-2">Mensajes</th>
                            <th class="text-right p-2">Mensajes Usuario</th>
                            <th class="text-right p-2">Mensajes Agente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->userStats as $user)
                            <tr>
                                <td class="border p-2">{{ $user->ip }}</td>
                                <td class="border p-2 text-right">{{ $user->total_messages }}</td>
                                <td class="border p-2 text-right">{{ $user->user_messages }}</td>
                                <td class="border p-2 text-right">{{ $user->agent_messages }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>

        <x-filament::card>
            <h2 class="text-lg font-bold mb-4">Estadísticas por Agente</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left p-2">Código de Agente</th>
                            <th class="text-right p-2">Total Conversaciones</th>
                            <th class="text-right p-2">Usuarios Únicos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->agentStats as $agent)
                            <tr>
                                <td class="border p-2">{{ $agent->agent_code }}</td>
                                <td class="border p-2 text-right">{{ $agent->total_conversations }}</td>
                                <td class="border p-2 text-right">{{ $agent->unique_users }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8">
        <x-filament::card>
            <h2 class="text-lg font-bold mb-4">Actividad Diaria</h2>
            <div id="daily-chart" style="height: 300px;"></div>
        </x-filament::card>

        <x-filament::card>
            <h2 class="text-lg font-bold mb-4">Usuarios Únicos por Día</h2>
            <div id="unique-users-chart" style="height: 300px;"></div>
        </x-filament::card>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            const dailyData = @json($this->dailyActivity);
            const uniqueUsersData = @json($this->uniqueUsersByDay);

            // Código para renderizar gráfico usando una biblioteca como ApexCharts
            // Aquí puedes integrar tu biblioteca de gráficos preferida

            // Ejemplo con ApexCharts
            if (typeof ApexCharts !== 'undefined') {
                // Gráfico de actividad diaria
                const dailyOptions = {
                    series: [{
                        name: 'Mensajes',
                        data: dailyData.map(day => day.total)
                    }],
                    chart: {
                        type: 'area',
                        height: 300
                    },
                    xaxis: {
                        categories: dailyData.map(day => day.date)
                    },
                    yaxis: {
                        title: {
                            text: 'Total Mensajes'
                        }
                    },
                    stroke: {
                        curve: 'smooth'
                    }
                };

                const chart = new ApexCharts(document.querySelector("#daily-chart"), dailyOptions);
                chart.render();

                // Gráfico de usuarios únicos por día
                const uniqueUsersOptions = {
                    series: [{
                        name: 'Usuarios Únicos',
                        data: uniqueUsersData.map(day => day.unique_users)
                    }],
                    chart: {
                        type: 'bar',
                        height: 300
                    },
                    xaxis: {
                        categories: uniqueUsersData.map(day => day.date)
                    },
                    yaxis: {
                        title: {
                            text: 'Usuarios Únicos'
                        }
                    },
                    colors: ['#2E93fA']
                };

                const usersChart = new ApexCharts(document.querySelector("#unique-users-chart"), uniqueUsersOptions);
                usersChart.render();
            }
        });
    </script>
</x-filament-panels::page>
<div class="modal fade" id="reportFiltersModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form class="modal-content" method="GET" action="{{ url()->current() }}">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-filter mr-1"></i> Filtros avanzados</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    @if (in_array('date_range', $definition['filters'], true))
                        <div class="form-group col-md-4">
                            <label>Fecha desde</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Fecha hasta</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                        </div>
                    @endif

                    @if (in_array('date', $definition['filters'], true))
                        <div class="form-group col-md-4">
                            <label>Fecha</label>
                            <input type="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="form-control">
                        </div>
                    @endif

                    @if (in_array('date_basis', $definition['filters'], true))
                        <div class="form-group col-md-4"><label>Filtrar fecha por</label><select name="date_basis" class="form-control"><option value="payment_date" @selected(request('date_basis', 'payment_date') === 'payment_date')>Fecha real de pago</option><option value="registered_at" @selected(request('date_basis') === 'registered_at')>Fecha de registro en sistema</option></select></div>
                    @endif

                    @if (in_array('include_historical', $definition['filters'], true))
                        <div class="form-group col-md-4"><label>Incluir históricos</label><select name="include_historical" class="form-control"><option value="1" @selected(request('include_historical', '1') === '1')>Sí</option><option value="0" @selected(request('include_historical') === '0')>No</option><option value="only" @selected(request('include_historical') === 'only')>Solo históricos</option></select></div>
                    @endif

                    @if (in_array('affects_cash', $definition['filters'], true))
                        <div class="form-group col-md-4"><label>Afecta caja</label><select name="affects_cash" class="form-control"><option value="all" @selected(request('affects_cash', 'all') === 'all')>Todos</option><option value="1" @selected(request('affects_cash') === '1')>Sí</option><option value="0" @selected(request('affects_cash') === '0')>No</option></select></div>
                    @endif

                    @if (in_array('cash_include_historical', $definition['filters'], true))
                        <div class="form-group col-md-4"><label>Incluir cobros históricos</label><select name="cash_include_historical" class="form-control"><option value="0" @selected(request('cash_include_historical', '0') === '0')>No, solo movimientos de Caja</option><option value="1" @selected(request('cash_include_historical') === '1')>Sí, como auditoría sin efecto</option></select></div>
                    @endif

                    @if (in_array('year', $definition['filters'], true))
                        <div class="form-group col-md-3">
                            <label>Anio</label>
                            <input type="number" min="2000" max="2100" name="year" value="{{ request('year', now()->year) }}" class="form-control">
                        </div>
                    @endif

                    @if (in_array('month', $definition['filters'], true))
                        <div class="form-group col-md-3">
                            <label>Mes</label>
                            <select name="month" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($months as $number => $name)
                                    <option value="{{ $number }}" @selected((string) request('month', now()->month) === (string) $number)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if (in_array('member', $definition['filters'], true) || in_array('member_required', $definition['filters'], true))
                        <div class="form-group col-md-6">
                            <label>Socio</label>
                            <select name="member_id" class="form-control report-select2">
                                <option value="">Seleccione un socio</option>
                                @foreach ($members as $member)
                                    <option value="{{ $member->id }}" @selected((string) request('member_id') === (string) $member->id)>
                                        {{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if (in_array('activity', $definition['filters'], true))
                        <div class="form-group col-md-6">
                            <label>Actividad</label>
                            <select name="activity_id" class="form-control report-select2">
                                <option value="">Todas</option>
                                @foreach ($activities as $activity)
                                    <option value="{{ $activity->id }}" @selected((string) request('activity_id') === (string) $activity->id)>
                                        {{ $activity->code }} - {{ $activity->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if (in_array('member_status', $definition['filters'], true))
                        <div class="form-group col-md-3">
                            <label>Estado socio</label>
                            <select name="status" class="form-control">
                                <option value="">Todos</option>
                                <option value="vigente" @selected(request('status') === 'vigente')>Vigente</option>
                                <option value="retirado" @selected(request('status') === 'retirado')>Retirado</option>
                                <option value="no_vigente" @selected(request('status') === 'no_vigente')>No vigente</option>
                            </select>
                        </div>
                    @elseif (in_array('status', $definition['filters'], true))
                        <div class="form-group col-md-3">
                            <label>Estado</label>
                            <input type="text" name="status" value="{{ request('status') }}" class="form-control" placeholder="registrado, anulado...">
                        </div>
                    @endif

                    @if (in_array('type', $definition['filters'], true))
                        <div class="form-group col-md-3">
                            <label>Tipo</label>
                            <select name="type" class="form-control">
                                <option value="">Todos</option>
                                <option value="ingreso" @selected(request('type') === 'ingreso')>Ingreso</option>
                                <option value="egreso" @selected(request('type') === 'egreso')>Egreso</option>
                            </select>
                        </div>
                    @endif

                    @if (in_array('payment_method', $definition['filters'], true))
                        <div class="form-group col-md-3">
                            <label>Metodo de pago</label>
                            <select name="payment_method" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($paymentMethods as $key => $label)
                                    <option value="{{ $key }}" @selected(request('payment_method') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if (in_array('category', $definition['filters'], true))
                        <div class="form-group col-md-4">
                            <label>Categoria</label>
                            <input type="text" name="category" value="{{ request('category') }}" class="form-control">
                        </div>
                    @endif

                    @if (in_array('civil_status', $definition['filters'], true))
                        <div class="form-group col-md-4">
                            <label>Estado civil</label>
                            <select name="civil_status" class="form-control">
                                <option value="">Todos</option>
                                @foreach (['soltero', 'casado', 'conviviente', 'divorciado', 'viudo'] as $status)
                                    <option value="{{ $status }}" @selected(request('civil_status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if (in_array('search', $definition['filters'], true))
                        <div class="form-group col-md-5">
                            <label>Busqueda</label>
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Socio, DNI o codigo">
                        </div>
                    @endif

                    @if (in_array('credit_status', $definition['filters'], true))
                        <div class="form-group col-md-3"><label>Calificación</label><select name="credit_status" class="form-control"><option value="">Todas</option>@foreach(['excelente','bueno','regular','riesgo','malo'] as $value)<option value="{{ $value }}" @selected(request('credit_status') === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
                    @endif
                    @if (in_array('member_type_credit', $definition['filters'], true))
                        <div class="form-group col-md-3"><label>Tipo de socio</label><select name="member_type" class="form-control"><option value="">Todos</option><option value="nuevo" @selected(request('member_type') === 'nuevo')>Nuevo</option><option value="antiguo" @selected(request('member_type') === 'antiguo')>Antiguo</option></select></div>
                    @endif
                    @if (in_array('has_late', $definition['filters'], true))
                        <div class="form-group col-md-3"><label>Con atrasos</label><select name="has_late" class="form-control"><option value="">Todos</option><option value="1" @selected(request('has_late') === '1')>Sí</option><option value="0" @selected(request('has_late') === '0')>No</option></select></div>
                    @endif
                    @if (in_array('has_overdue', $definition['filters'], true))
                        <div class="form-group col-md-3"><label>Deuda vencida</label><select name="has_overdue" class="form-control"><option value="">Todos</option><option value="1" @selected(request('has_overdue') === '1')>Sí</option><option value="0" @selected(request('has_overdue') === '0')>No</option></select></div>
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ url()->current() }}" class="btn btn-light border"><i class="fas fa-undo mr-1"></i> Limpiar</a>
                <button type="submit" class="btn btn-dark"><i class="fas fa-search mr-1"></i> Aplicar filtros</button>
            </div>
        </form>
    </div>
</div>

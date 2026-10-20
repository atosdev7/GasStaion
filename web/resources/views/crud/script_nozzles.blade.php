@push('scripts')
    <script>
        // The fuel type and the tank of a nozzle go together: choosing a tank sets the fuel type of the nozzle to the tank's; choosing a fuel type
        // leaves only the tanks that hold it (and picks the first of them when the chosen tank holds another fuel type).
        $(function () {
            var fuels = @json($fields['tank_id']['fuels']);
            var fuel = $('#f_fuel_type');
            var tank = $('#f_tank_id');

            function matching() {
                var kind = fuel.val();
                tank.find('option').each(function () {
                    $(this).prop('disabled', fuels[this.value] !== undefined && fuels[this.value] !== kind);
                });
                if (tank.find('option:selected').prop('disabled')) {
                    var first = tank.find('option').filter(function () { return !this.disabled; }).first();
                    if (first.length) {
                        tank.val(first.val());
                    }
                }
            }

            tank.on('change', function () {
                if (fuels[tank.val()] !== undefined) {
                    fuel.val(fuels[tank.val()]);
                    matching();
                }
            });
            fuel.on('change', matching);
            if (!@json($record->exists)) {
                // a new nozzle: the fuel type follows the tank that is shown first
                if (fuels[tank.val()] !== undefined) {
                    fuel.val(fuels[tank.val()]);
                }
            }
            matching();
        });
    </script>
@endpush

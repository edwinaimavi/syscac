document.addEventListener('DOMContentLoaded', function () {
    const reportTable = $('#reportTable');

    if ($.fn.DataTable && reportTable.length && !$.fn.DataTable.isDataTable(reportTable[0])) {
        reportTable.DataTable({
            responsive: true,
            pageLength: 25,
            order: [],
            language: {
                url: '/vendor/datatables/js/i18n/es-ES.json',
                emptyTable: 'No hay datos disponibles'
            }
        });
    }

    if ($.fn.select2) {
        $('.report-select2').select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $('#reportFiltersModal')
        });
    }
});

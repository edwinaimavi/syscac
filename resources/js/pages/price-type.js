
let tablePriceType;

const submitLocks = {
    priceTypeSave: false
};

function lock(action) {
    if (submitLocks[action]) return false;
    submitLocks[action] = true;
    return true;
}

function unlock(action) {
    submitLocks[action] = false;
}

document.addEventListener("DOMContentLoaded", function () {

    // ============================
    // DATATABLE PRICE TYPES
    // ============================
    tablePriceType = $('#tablePriceType').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.priceTypeList,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'name' },
            { data: 'code' },
            { data: 'status', orderable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        language: {
            url: "/vendor/datatables/js/i18n/es-ES.json"
        },
        preDrawCallback: function () {
            divLoading && divLoading.classList.remove('d-none');
        },
        drawCallback: function () {
            divLoading && divLoading.classList.add('d-none');
        }
    });

    // ============================
    // CSRF
    // ============================
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // ============================
    // GUARDAR / EDITAR
    // ============================
    $('#priceTypeForm').on('submit', function (e) {
        e.preventDefault();

        if (!lock('priceTypeSave')) return;

        let $form = $(this);
        let id = $form.attr('data-id');

        let formData = new FormData(this);

        let url = '/admin/price-types';
        let method = 'POST';

        if (id) {
            url = `/admin/price-types/${id}`;
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,

            success: function (res) {

                unlock('priceTypeSave');

                $('#priceTypeModal').modal('hide');
                $form.trigger('reset').removeAttr('data-id');

                tablePriceType.ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title: res.message || 'Tipo de precio guardado',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
            },

            error: function (xhr) {

                unlock('priceTypeSave');

                if (xhr.status === 422) {

                    const errors = xhr.responseJSON.errors;

                    $('.is-invalid').removeClass('is-invalid');
                    $('.invalid-feedback').text('');

                    for (let field in errors) {
                        $('#' + field).addClass('is-invalid');
                        $('#' + field + '-error').text(errors[field][0]);
                    }

                } else {
                    Swal.fire('Error', 'Error al guardar el tipo de precio', 'error');
                }
            }
        });
    });

    // ============================
    // EDITAR
    // ============================
    $(document).on('click', '.editPriceType', function () {

        const $btn = $(this);

        $('#priceTypeForm').attr('data-id', $btn.data('id'));
        $('#name').val($btn.data('name'));
        $('#code').val($btn.data('code'));
        $('#status').prop('checked', $btn.data('status') == 1);

        $('#priceTypeModalLabel').text('Editar Tipo de Precio');

        $('#priceTypeModal').modal('show');
    });

    // ============================
    // RESET MODAL
    // ============================
    $('#priceTypeModal').on('show.bs.modal', function () {

        const $form = $('#priceTypeForm');

        if (!$form.attr('data-id')) {

            $form[0].reset();

            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('');

            $('#status').prop('checked', true);
            $('#priceTypeModalLabel').text('Nuevo Tipo de Precio');
        }
    });

    // ============================
    // ELIMINAR
    // ============================
    $(document).on('click', '.deletePriceType', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar tipo de precio?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: `/admin/price-types/${id}`,
                type: 'DELETE',

                success: function (res) {

                    tablePriceType.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Tipo de precio eliminado',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                },

                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar', 'error');
                }
            });
        });
    });

    // ============================
    // LIMPIAR MODAL AL CERRAR
    // ============================
    $('#priceTypeModal').on('hidden.bs.modal', function () {

        const $form = $('#priceTypeForm');

        // reset form
        $form.trigger('reset');

        // quitar modo edición
        $form.removeAttr('data-id');

        // limpiar validaciones
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').text('');

        // estado por defecto
        $('#status').prop('checked', true);

        // título
        $('#priceTypeModalLabel').text('Nuevo Tipo de Precio');
    });

});
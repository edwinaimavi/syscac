let tableSupplier;

const submitLocks = {
    supplierSave: false
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
    // DATATABLE SUPPLIERS
    // ============================
    tableSupplier = $('#tableSupplier').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.supplierList,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'name' },
            { data: 'ruc' },
            { data: 'phone' },
            { data: 'email' },
            { data: 'status', orderable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        language: {
            url: "/vendor/datatables/js/i18n/es-ES.json"
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
    // GUARDAR / EDITAR SUPPLIER
    // ============================
    $('#supplierForm').on('submit', function (e) {
        e.preventDefault();

        if (!lock('supplierSave')) return;

        let $form = $(this);
        let id = $form.attr('data-id');

        let formData = new FormData(this);

        let url;
        let method = 'POST';

        if (id) {
            url = `/admin/suppliers/${id}`;
            formData.append('_method', 'PUT');
        } else {
            url = '/admin/suppliers';
        }

        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,

            success: function (res) {

                unlock('supplierSave');

                $('#supplierModal').modal('hide');
                $form.trigger('reset').removeAttr('data-id');

                tableSupplier.ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title: res.message || 'Proveedor guardado',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
            },

            error: function (xhr) {

                unlock('supplierSave');

                if (xhr.status === 422) {

                    const errors = xhr.responseJSON.errors;

                    $('.is-invalid').removeClass('is-invalid');
                    $('.invalid-feedback').text('');

                    for (let field in errors) {
                        $('#' + field).addClass('is-invalid');
                        $('#' + field + '-error').text(errors[field][0]);
                    }

                } else {
                    Swal.fire('Error', 'Error al guardar el proveedor', 'error');
                }
            }
        });

    });

    // ============================
    // EDITAR SUPPLIER
    // ============================
    $(document).on('click', '.editSupplier', function () {

        const $btn = $(this);

        $('#supplierForm').attr('data-id', $btn.data('id'));

        $('#name').val($btn.data('name'));
        $('#ruc').val($btn.data('ruc'));
        $('#phone').val($btn.data('phone'));
        $('#email').val($btn.data('email'));
        $('#address').val($btn.data('address'));
        $('#status').prop('checked', $btn.data('status') == 1);

        $('#supplierModal').modal('show');
    });

    // ============================
    // RESET MODAL
    // ============================
    $('#supplierModal').on('show.bs.modal', function () {

        const $form = $('#supplierForm');

        if (!$form.attr('data-id')) {

            $form[0].reset();

            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('');

            $('#status').prop('checked', true);
        }
    });

    // ============================
    // ELIMINAR SUPPLIER
    // ============================
    $(document).on('click', '.deleteSupplier', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar proveedor?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: `/admin/suppliers/${id}`,
                type: 'DELETE',

                success: function (res) {

                    tableSupplier.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Proveedor eliminado',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                },

                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar el proveedor', 'error');
                }
            });
        });
    });

});
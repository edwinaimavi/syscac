let tableCustomer;

const submitLocks = {
    customerSave: false
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
    // DATATABLE CUSTOMERS
    // ============================
    tableCustomer = $('#tableCustomer').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.customerList,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'full_name' },
            { data: 'document_type' },
            { data: 'document_number' },
            { data: 'phone' },
            { data: 'email' },
            { data: 'status_badge', orderable: false },
            { data: 'actions', orderable: false, searchable: false }
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
    // GUARDAR / ACTUALIZAR
    // ============================
    $('#customerForm').on('submit', function (e) {
        e.preventDefault();

        if (!lock('customerSave')) return;

        let $form = $(this);
        let id = $form.attr('data-id');
        $('#document_type').prop('disabled', false);

        let formData = $form.serialize();

        let url;
        let method = 'POST';

        if (id) {
            url = `/admin/customers/${id}`;
            formData += '&_method=PUT';
        } else {
            url = '/admin/customers';
        }

        $.ajax({
            url: url,
            type: method,
            data: formData,

            success: function (res) {

                unlock('customerSave');

                $('#customerModal').modal('hide');
                $form.trigger('reset').removeAttr('data-id');

                tableCustomer.ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title: res.message || 'Cliente guardado',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
            },

            error: function (xhr) {

                unlock('customerSave');

                if (xhr.status === 422) {

                    const errors = xhr.responseJSON.errors;

                    $('.is-invalid').removeClass('is-invalid');
                    $('.invalid-feedback').text('');

                    for (let field in errors) {
                        $('#' + field).addClass('is-invalid');
                        $('#' + field + '-error').text(errors[field][0]);
                    }

                } else {
                    Swal.fire('Error', 'Error al guardar el cliente', 'error');
                }
            }
        });

    });

    // ============================
    // EDITAR
    // ============================
    $(document).on('click', '.btn-edit', function () {

        const $btn = $(this);

        $('#customerForm').attr('data-id', $btn.data('id'));

        $('#first_name').val($btn.data('first_name'));
        $('#last_name').val($btn.data('last_name'));
        $('#document_type').val($btn.data('document_type'));
        $('#document_number').val($btn.data('document_number'));
        $('#phone').val($btn.data('phone'));
        $('#email').val($btn.data('email'));
        $('#address').val($btn.data('address'));
        $('#status').prop('checked', $btn.data('status') == 1);

        $('#customerModal').modal('show');
    });

    // ============================
    // RESET MODAL
    // ============================
    $('#customerModal').on('show.bs.modal', function () {

        const $form = $('#customerForm');

        if (!$form.attr('data-id')) {

            $form[0].reset();

            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('');

            $('#status').prop('checked', true);
        }
    });

    // ============================
    // ELIMINAR
    // ============================
    $(document).on('click', '.btn-delete', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar cliente?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: `/admin/customers/${id}`,
                type: 'DELETE',

                success: function (res) {

                    tableCustomer.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Cliente eliminado',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                },

                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar el cliente', 'error');
                }
            });
        });
    });


    function toggleFields() {

        let personType = $('#person_type').val();
        let docType = $('#document_type').val();

        // RESET
        $('#naturalFields').addClass('d-none');
        $('#businessFields').addClass('d-none');

        // =========================
        // PERSONA NATURAL
        // =========================
        if (personType === 'natural') {

            // habilitar selector
            $('#document_type').prop('disabled', false);

            if (docType === 'DNI' || docType === 'CE') {

                $('#naturalFields').removeClass('d-none');

            }

            if (docType === 'RUC') {

                $('#businessFields').removeClass('d-none');

            }

        }

        // =========================
        // PERSONA JURÍDICA
        // =========================
        if (personType === 'juridica') {

            // 🔥 dejar solo RUC
            $('#document_type')
                .html('<option value="RUC">RUC</option>')
                .val('RUC')
                .prop('disabled', true);

            $('#businessFields').removeClass('d-none');
        }
    }

    // EVENTOS
    $('#person_type, #document_type').on('change', function () {
        toggleFields();
    });

    // INICIAL
    toggleFields();

    $('#person_type').on('change', function () {

        if ($(this).val() === 'juridica') {

            $('#document_type')
                .html('<option value="RUC">RUC</option>')
                .val('RUC')
                .prop('disabled', true);

        } else {

            // 🔥 restaurar opciones
            $('#document_type')
                .html(`
                <option value="">-- Seleccionar --</option>
                <option value="DNI">DNI</option>
                <option value="CE">CE</option>
                <option value="RUC">RUC</option>
            `)
                .prop('disabled', false);
        }

        toggleFields();
    });


    // ==============================
    // CONSULTA DNI / RUC
    // ==============================

    const $documentType = $('#document_type');
    const $documentNumber = $('#document_number');

    function buscarDocumento() {

        let tipo = $documentType.val();
        let numero = $documentNumber.val().trim();

        let personType = $('#person_type').val();

        // ====================================
        // VALIDAR PERSONA JURIDICA
        // ====================================

        // ====================================
        // VALIDACIONES DOCUMENTOS
        // ====================================

        // 🔥 PERSONA JURIDICA
        if (personType === 'juridica') {

            // Debe tener 11 dígitos
            if (numero.length !== 11) {

                Swal.fire({
                    icon: 'warning',
                    title: 'RUC inválido',
                    text: 'El RUC debe tener 11 dígitos'
                });

                return;
            }

            // Debe empezar con 20
            if (!numero.startsWith('20')) {

                Swal.fire({
                    icon: 'warning',
                    title: 'RUC inválido',
                    text: 'Una empresa debe tener un RUC que empiece con 20'
                });

                return;
            }
        }

        // 🔥 PERSONA NATURAL
        if (personType === 'natural') {

            // ======================
            // DNI
            // ======================
            if (tipo === 'DNI') {

                if (numero.length !== 8) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'DNI inválido',
                        text: 'El DNI debe tener 8 dígitos'
                    });

                    return;
                }
            }

            // ======================
            // RUC NATURAL
            // ======================
            if (tipo === 'RUC') {

                // Debe tener 11
                if (numero.length !== 11) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'RUC inválido',
                        text: 'El RUC debe tener 11 dígitos'
                    });

                    return;
                }

                // Debe empezar con 10
                if (!numero.startsWith('10')) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'RUC inválido',
                        text: 'Una persona natural con RUC debe empezar con 10'
                    });

                    return;
                }
            }
        }

        if (!numero || !/^\d+$/.test(numero)) return;

        if (numero.length !== 8 && numero.length !== 11) return;

        let url = window.routes.consultarDocumento.replace('DOC_PLACEHOLDER', numero);

        $.ajax({
            url: url,
            type: 'GET',

            success: function (res) {

                if (!res.status) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'No encontrado',
                        text: res.message || 'No se encontró el documento',
                    });

                    return;
                }

                // ======================
                // DNI
                // ======================
                if (res.type === 'DNI') {

                    let p = res.data;

                    $('#first_name').val(p.nombres || '');
                    $('#last_name').val(
                        (p.apellidoPaterno || '') + ' ' + (p.apellidoMaterno || '')
                    );

                    $('#business_name').val('');
                }

                // ======================
                // RUC
                // ======================
                if (res.type === 'RUC') {

                    let e = res.data;

                    $('#business_name').val(e.razonSocial || e.nombre || '');

                    $('#first_name').val('');
                    $('#last_name').val('');
                    $('#address').val(
                        (e.direccion || '') +
                        ' - ' + (e.distrito || '') +
                        ' - ' + (e.provincia || '') +
                        ' - ' + (e.departamento || '')
                    );

                    // forzar tipo documento
                    $('#document_type').val('RUC');

                    // opcional: autoseleccionar jurídica
                    $('#person_type').val('juridica').trigger('change');
                }
            },

            error: function (xhr) {

                let msg = 'No se pudo consultar el documento';

                // 🔥 mensaje backend
                if (xhr.responseJSON) {

                    // mensaje normal
                    if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }

                    // errores laravel
                    if (xhr.responseJSON.errors) {

                        let firstError = Object.values(xhr.responseJSON.errors)[0];

                        if (Array.isArray(firstError)) {
                            msg = firstError[0];
                        }
                    }
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'Documento no encontrado',
                    text: msg,
                });

                // 🔥 limpiar campos
                $('#first_name').val('');
                $('#last_name').val('');
                $('#business_name').val('');
                $('#address').val('');
            }
        });
    }

    // ENTER
    $documentNumber.on('keyup', function (e) {
        if (e.key === 'Enter') {
            buscarDocumento();
        }
    });

    // AL SALIR
    $documentNumber.on('blur', function () {
        buscarDocumento();
    });

});
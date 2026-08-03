let tablePurchase;

// ============================
// LOCK
// ============================
const submitLocks = {
    purchaseSave: false
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
    // DATATABLE
    // ============================
    tablePurchase = $('#tablePurchase').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.purchaseList,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'supplier' },
            { data: 'document_type' },
            { data: 'document_number' },
            { data: 'date' },
            { data: 'total' },
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
    // 🔥 DETALLE COMPRA
    // ============================
    const products = window.products || [];

    // 👉 AGREGAR FILA
    document.getElementById('addRow').addEventListener('click', function () {

        let options = `<option value="">Seleccione</option>`;
        products.forEach(p => {
            options += `<option value="${p.id}">${p.name}</option>`;
        });

        let row = `
            <tr>
                <td>
                    <select name="product_id[]" class="form-control form-control-sm">
                        ${options}
                    </select>
                </td>

                <td>
                    <input type="number" name="quantity[]" class="form-control form-control-sm quantity" min="1" value="1">
                </td>

                <td>
                    <input type="number" name="cost[]" class="form-control form-control-sm cost" step="0.01" value="0">
                </td>

                <td>
                    <input type="text" class="form-control form-control-sm subtotal" readonly value="0.00">
                </td>

                <td>
                    <button type="button" class="btn btn-danger btn-sm removeRow">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        document.getElementById('purchaseDetailBody').insertAdjacentHTML('beforeend', row);
    });

    // 👉 CALCULAR SUBTOTAL
    document.addEventListener('input', function (e) {

        if (e.target.classList.contains('quantity') || e.target.classList.contains('cost')) {

            let row = e.target.closest('tr');

            let qty = parseFloat(row.querySelector('.quantity').value) || 0;
            let cost = parseFloat(row.querySelector('.cost').value) || 0;

            let subtotal = qty * cost;

            row.querySelector('.subtotal').value = subtotal.toFixed(2);

            calculateTotal();
        }
    });

    // 👉 ELIMINAR FILA
    document.addEventListener('click', function (e) {

        if (e.target.closest('.removeRow')) {
            e.target.closest('tr').remove();
            calculateTotal();
        }
    });

    // 👉 CALCULAR TOTAL
    function calculateTotal() {

        let total = 0;

        document.querySelectorAll('.subtotal').forEach(el => {
            total += parseFloat(el.value) || 0;
        });

        document.getElementById('totalPurchase').innerText = `S/ ${total.toFixed(2)}`;

        let totalInput = document.getElementById('total');

        if (!totalInput) {
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'total';
            input.id = 'total';
            document.getElementById('purchaseForm').appendChild(input);
            totalInput = input;
        }

        totalInput.value = total.toFixed(2);
    }

    // ============================
    // GUARDAR PURCHASE
    // ============================
    $('#purchaseForm').on('submit', function (e) {
        e.preventDefault();

        if (!lock('purchaseSave')) return;

        let $form = $(this);
        let id = $form.attr('data-id');

        let formData = new FormData(this);

        let url;
        let method = 'POST';

        if (id) {
            url = `/admin/purchases/${id}`;
            formData.append('_method', 'PUT');
        } else {
            url = '/admin/purchases';
        }

        $.ajax({

            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,

            success: function (res) {

                unlock('purchaseSave');

                $('#purchaseModal').modal('hide');
                $form.trigger('reset').removeAttr('data-id');

                // limpiar tabla detalle
                document.getElementById('purchaseDetailBody').innerHTML = '';
                document.getElementById('totalPurchase').innerText = 'S/ 0.00';

                tablePurchase.ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title: res.message || 'Compra guardada',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
            },

            error: function (xhr) {

                unlock('purchaseSave');

                if (xhr.status === 422) {

                    const errors = xhr.responseJSON.errors;

                    $('.is-invalid').removeClass('is-invalid');
                    $('.invalid-feedback').text('');

                    for (let field in errors) {
                        $('#' + field).addClass('is-invalid');
                        $('#' + field + '-error').text(errors[field][0]);
                    }

                } else {
                    Swal.fire('Error', 'Error al guardar la compra', 'error');
                }
            }
        });
    });

    // ============================
    // EDITAR
    // ============================
    $(document).on('click', '.editPurchase', function () {

        let id = $(this).data('id');

        $.get(`/admin/purchases/${id}`, function (res) {

            let purchase = res.purchase;
            let details = res.details;

            // llenar cabecera
            $('#purchaseForm').attr('data-id', purchase.id);
            $('#supplier_id').val(purchase.supplier_id);
            $('#document_type').val(purchase.document_type);
            $('#document_number').val(purchase.document_number);
            $('#date').val(purchase.date);

            // limpiar tabla
            $('#purchaseDetailBody').html('');

            let options = `<option value="">Seleccione</option>`;
            window.products.forEach(p => {
                options += `<option value="${p.id}">${p.name}</option>`;
            });

            // 🔥 llenar detalles
            details.forEach(d => {

                let row = `
                <tr>
                    <td>
                        <select name="product_id[]" class="form-control form-control-sm">
                            ${options}
                        </select>
                    </td>
                    <td>
                        <input type="number" name="quantity[]" value="${d.quantity}" class="form-control form-control-sm quantity">
                    </td>
                    <td>
                        <input type="number" name="cost[]" value="${d.cost}" class="form-control form-control-sm cost">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm subtotal" value="${d.subtotal}" readonly>
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm removeRow">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;

                $('#purchaseDetailBody').append(row);

                // seleccionar producto
                $('#purchaseDetailBody tr:last select').val(d.product_id);
            });

            // recalcular total
            calculateTotal();

            $('#purchaseModal').modal('show');
        });

    });

    // ============================
    // RESET MODAL
    // ============================
    $('#purchaseModal').on('show.bs.modal', function (e) {

        const $form = $('#purchaseForm');

        const isEdit = $form.attr('data-id');

        if (!isEdit) {

            $form[0].reset();

            document.getElementById('purchaseDetailBody').innerHTML = '';
            document.getElementById('totalPurchase').innerText = 'S/ 0.00';

            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('');

            $('#purchaseModalLabel').text('Nueva Compra');
        }
    });

    // ============================
    // ELIMINAR
    // ============================
    $(document).on('click', '.deletePurchase', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar compra?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: `/admin/purchases/${id}`,
                type: 'DELETE',

                success: function (res) {

                    tablePurchase.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Compra eliminada',
                        toast: true,
                        position: 'top-end',
                        timer: 3000,
                        showConfirmButton: false
                    });
                },

                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar la compra', 'error');
                }
            });
        });
    });

    $('.btn-new').on('click', function () {

        const $form = $('#purchaseForm');

        // 🔥 FORZAR MODO NUEVO
        $form.removeAttr('data-id');

        // 🔥 LIMPIAR TODO
        $form[0].reset();

        $('#purchaseDetailBody').html('');
        $('#totalPurchase').text('S/ 0.00');

    });

    $('#purchaseModal').on('hidden.bs.modal', function () {

        const $form = $('#purchaseForm');

        $form.removeAttr('data-id');
        $form[0].reset();

        $('#purchaseDetailBody').html('');
        $('#totalPurchase').text('S/ 0.00');

    });


    // ============================
    // 👁️ VER COMPRA
    // ============================
    $(document).on('click', '.viewPurchase', function () {

        let id = $(this).data('id');

        $.get(`/admin/purchases/${id}`, function (res) {

            let purchase = res.purchase;
            let details = res.details;

            // ============================
            // CABECERA
            // ============================
            $('#viewSupplier').text(purchase.supplier?.name ?? '—');
            $('#viewDocumentType').text(purchase.document_type);
            $('#viewDocumentNumber').text(purchase.document_number);
            $('#viewDate').text(purchase.date);

            $('#viewStatus').html(
                purchase.status
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Anulado</span>'
            );

            // ============================
            // DETALLE
            // ============================
            let rows = '';

            details.forEach(d => {

                // 🔥 buscar nombre del producto
                let product = window.products.find(p => p.id == d.product_id);

                rows += `
                <tr>
                    <td>${product ? product.name : 'Producto eliminado'}</td>
                    <td>${d.quantity}</td>
                    <td>S/ ${parseFloat(d.cost).toFixed(2)}</td>
                    <td>S/ ${parseFloat(d.subtotal).toFixed(2)}</td>
                </tr>
            `;
            });

            $('#viewPurchaseDetails').html(rows);

            // ============================
            // TOTAL
            // ============================
            $('#viewTotal').text(`S/ ${parseFloat(purchase.total).toFixed(2)}`);

            // ============================
            // MOSTRAR MODAL
            // ============================
            $('#purchaseViewModal').modal('show');

        });

    });

});
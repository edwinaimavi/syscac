import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
var divLoading = document.getElementById('divLoading');
let tableCategory;
let removeImageFlag = false;

// ============================
// LOCK ANTI DOBLE CLICK
// ============================
const submitLocks = {
    categorySave: false
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

    let IMG_WIDTH = 500;
    let IMG_HEIGHT = 400;

    const uploadBox = document.getElementById('uploadBox');

    if (uploadBox) {
        IMG_WIDTH = parseInt(uploadBox.dataset.width || 500);
        IMG_HEIGHT = parseInt(uploadBox.dataset.height || 400);
    }

    // Mostrar en UI
    const sizeText = document.getElementById('imgSizeText');
    if (sizeText) {
        sizeText.innerText = `${IMG_WIDTH}x${IMG_HEIGHT}`;
    }

    // CSRF
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    //VARIABLES  PARA EL LLENADO AUTOMATICO DEL CAMPO SLUG
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');


    function generateSlug(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .normalize('NFD')                 // elimina acentos
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')     // elimina caracteres raros
            .replace(/\s+/g, '-')              // espacios por guiones
            .replace(/-+/g, '-');              // evita guiones dobles
    }

    nameInput.addEventListener('input', function () {
        slugInput.value = generateSlug(this.value);
    });



    // ============================
    // DATATABLE CATEGORIES
    // ============================
    tableCategory = $('#tableCategory').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.categoryList,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'name' },
            { data: 'parent', name: 'parent', orderable: false },
            { data: 'description' },
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
    // EDITAR CATEGORY
    // ============================
    $(document).on('click', '.editCategory', function () {

        const $btn = $(this);

        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const uploadBox = document.getElementById('uploadBox');
        const imageInput = document.getElementById('image');

        // 🔥 RESET TOTAL PRIMERO (IMPORTANTE)
        previewImg.src = '';
        imagePreview.classList.add('d-none');
        uploadPlaceholder.style.display = 'block';
        uploadBox.classList.remove('disabled');
        imageInput.value = '';
        removeImageFlag = false;

        // 🔥 SET DATA
        $('#categoryForm').attr('data-id', $btn.data('id'));
        $('#name').val($btn.data('name'));
        $('#slug').val($btn.data('slug'));
        $('#description').val($btn.data('description'));
        $('#status').prop('checked', $btn.data('status') == 1);
        $('#categoryForm select[name="parent_id"]').val($btn.data('parent'));

        const image = $btn.data('image');

        // 🔥 AHORA SI APLICA IMAGEN
        if (image && image !== 'null' && image !== '') {

            previewImg.src = '/storage/' + image;

            imagePreview.classList.remove('d-none');
            uploadPlaceholder.style.display = 'none';

            uploadBox.classList.add('no-upload');
        }

        $('#categoryModal').modal('show');
    });


    $('#categoryModal').on('show.bs.modal', function () {
        const $form = $('#categoryForm');

        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const uploadBox = document.getElementById('uploadBox');
        const imageInput = document.getElementById('image');

        if (!$form.attr('data-id')) {

            // 🔥 RESET FORM
            $form[0].reset();

            // 🔥 LIMPIAR ERRORES
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('');

            // 🔥 ESTADO
            $('#status').prop('checked', true);
            $('#categoryModalLabel').text('Nueva Categoría');

            // 🔥 RESET IMAGEN COMPLETO
            previewImg.src = '';
            imagePreview.classList.add('d-none');
            uploadPlaceholder.style.display = 'block';
            uploadBox.classList.remove('disabled');
            imageInput.value = '';

            // 🔥 RESET FLAG
            removeImageFlag = false;
        }
    });

    // ============================
    //   GUARDAR / ACTUALIZAR CATEGORÍA
    // ============================
    $('#categoryForm').on('submit', function (e) {
        e.preventDefault();

        // ⛔ evitar doble envío
        if (!lock('categorySave')) return;

        divLoading && (divLoading.style.display = 'flex');

        const $form = $(this);
        const id = $form.attr('data-id');

        let url, method;
        let formData = new FormData(this);
        if (removeImageFlag) {
            formData.append('remove_image', 1);
        }

        if (id) {
            url = `/admin/categories/${id}`;
            method = 'POST';
        } else {
            url = window.routes.storeCategory; // define esto en blade
            method = 'POST';
        }

        if (id) {
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false, // 🔥 IMPORTANTE
            contentType: false, // 🔥 IMPORTANTE
            success: function (response) {
                divLoading && (divLoading.style.display = 'none');
                unlock('categorySave');

                $('#categoryModal').modal('hide');
                $form.trigger('reset').removeAttr('data-id');

                tableCategory && tableCategory.ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Categoría guardada correctamente',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            },
            error: function (xhr) {
                divLoading && (divLoading.style.display = 'none');
                unlock('categorySave');

                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors || {};

                    $form.find('.is-invalid').removeClass('is-invalid');
                    $form.find('.invalid-feedback').text('');

                    $.each(errors, function (field, messages) {
                        const input = $('#' + field);
                        if (input.length) {
                            input.addClass('is-invalid');
                            $('#' + field + '-error').text(messages[0]);
                        }
                    });
                } else {
                    console.error('Error al guardar categoría', xhr);

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Ocurrió un error al guardar la categoría.'
                    });
                }
            }
        });
    });


    // ============================
    //   LIMPIAR MODAL CATEGORÍA
    // ============================
    $('#categoryModal').on('hidden.bs.modal', function () {
        const $form = $('#categoryForm');

        // Reset campos
        $form.trigger('reset');

        // Quitar modo edición
        $form.removeAttr('data-id');

        // Limpiar errores de validación
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').text('');

        // Estado por defecto
        $('#status').prop('checked', true);

        // Slug editable nuevamente
        $('#slug').prop('readonly', false);
    });


    // ============================
    // ELIMINAR CATEGORY
    // ============================
    $(document).on('click', '.deleteCategory', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar categoría?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: `/admin/categories/${id}`,
                type: 'DELETE',
                success: function (res) {
                    tableCategory.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Categoría eliminada',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                },
                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar la categoría', 'error');
                }
            });
        });
    });


    let cropper;
    const imageInput = document.getElementById('image');
    const imageToCrop = document.getElementById('imageToCrop');

    imageInput.addEventListener('change', function (e) {

        let file = e.target.files[0];

        if (!file) return;

        let reader = new FileReader();

        reader.onload = function (event) {

            imageToCrop.src = event.target.result;

            $('#cropModal').modal('show');

            $('#cropModal').one('shown.bs.modal', function () {

                if (cropper) cropper.destroy();

                cropper = new Cropper(imageToCrop, {
                    aspectRatio: IMG_WIDTH / IMG_HEIGHT,
                    viewMode: 1,
                    dragMode: 'move',

                    cropBoxMovable: false,
                    cropBoxResizable: false,

                    movable: true,
                    zoomable: true,
                    scalable: false,
                    rotatable: false,

                    autoCropArea: 1,
                    background: false,

                    ready() {
                        let containerData = cropper.getContainerData();
                        let imageData = cropper.getImageData();

                        let scaleX = containerData.width / imageData.naturalWidth;
                        let scaleY = containerData.height / imageData.naturalHeight;

                        let scale = Math.max(scaleX, scaleY);

                        cropper.zoomTo(scale);
                    }
                });

            });
        };

        reader.readAsDataURL(file);



    });

    document.getElementById('cropImageBtn').addEventListener('click', function () {

        let canvas = cropper.getCroppedCanvas({
            width: IMG_WIDTH,
            height: IMG_HEIGHT
        });

        let croppedImage = canvas.toDataURL('image/jpeg');

        // Mostrar preview
        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');

        // Mostrar imagen
        previewImg.src = croppedImage;

        // Mostrar preview y ocultar placeholder
        imagePreview.classList.remove('d-none');
        uploadPlaceholder.style.display = 'none';

        // 👉 Convertir a archivo para enviar al backend
        canvas.toBlob(function (blob) {
            let file = new File([blob], "category.jpg", { type: "image/jpeg" });

            let dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);

            imageInput.files = dataTransfer.files;
        });

        $('#cropModal').modal('hide');

    });

    // CLICK EN EL BOX → ABRE INPUT FILE
    document.getElementById('uploadBox').addEventListener('click', function () {

        if (this.classList.contains('disabled')) return; // 🔥

        document.getElementById('image').click();
    });


    //eliminar imagen 
    document.getElementById('removeImage').addEventListener('click', function () {

        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const uploadBox = document.getElementById('uploadBox');
        const imageInput = document.getElementById('image');

        previewImg.src = '';
        imagePreview.classList.add('d-none');
        uploadPlaceholder.style.display = 'block';
        uploadBox.classList.remove('disabled');

        imageInput.value = '';

        // 🔥 IMPORTANTE
        removeImageFlag = true;
    });
   /*  const uploadBox = document.getElementById('uploadBox'); */

    uploadBox.addEventListener('dragover', function (e) {
        e.preventDefault();
        uploadBox.classList.add('dragging');
    });

    uploadBox.addEventListener('dragleave', function () {
        uploadBox.classList.remove('dragging');
    });

    uploadBox.addEventListener('drop', function (e) {
        e.preventDefault();
        uploadBox.classList.remove('dragging');

        let files = e.dataTransfer.files;

        if (files.length > 0) {
            imageInput.files = files;

            // 🔥 DISPARAR EVENTO CHANGE MANUAL
            imageInput.dispatchEvent(new Event('change'));
        }
    });








});

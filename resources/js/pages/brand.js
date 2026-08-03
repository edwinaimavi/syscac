import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
let tableBrand;
let cropper;
let removeImageFlag = false;

const submitLocks = {
    brandSave: false
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
    // DATATABLE BRANDS
    // ============================
    tableBrand = $('#tableBrand').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.brandList,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'name' },
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
    // CONFIG IMAGEN
    // ============================
    let IMG_WIDTH = 500;
    let IMG_HEIGHT = 400;

    const uploadBox = document.getElementById('uploadBox');

    if (uploadBox) {
        IMG_WIDTH = parseInt(uploadBox.dataset.width || 500);
        IMG_HEIGHT = parseInt(uploadBox.dataset.height || 400);
    }

    const sizeText = document.getElementById('imgSizeText');
    if (sizeText) {
        sizeText.innerText = `${IMG_WIDTH}x${IMG_HEIGHT}`;
    }

    // ============================
    // CSRF
    // ============================
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // ============================
    // SLUG AUTOMÁTICO
    // ============================
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');

    function generateSlug(text) {
        return text
            .toLowerCase()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }

    nameInput.addEventListener('input', function () {
        slugInput.value = generateSlug(this.value);
    });

    // ============================
    // CROPPER
    // ============================
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
                    autoCropArea: 1,
                    background: false
                });

            });
        };

        reader.readAsDataURL(file);
    });

    // ============================
    // RECORTAR IMAGEN
    // ============================
    document.getElementById('cropImageBtn').addEventListener('click', function () {

        let canvas = cropper.getCroppedCanvas({
            width: IMG_WIDTH,
            height: IMG_HEIGHT
        });

        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');

        previewImg.src = canvas.toDataURL('image/jpeg');

        imagePreview.classList.remove('d-none');
        uploadPlaceholder.style.display = 'none';

        canvas.toBlob(function (blob) {
            let file = new File([blob], "brand.jpg", { type: "image/jpeg" });

            let dt = new DataTransfer();
            dt.items.add(file);

            imageInput.files = dt.files;
        });

        $('#cropModal').modal('hide');
    });

    // ============================
    // CLICK SUBIR IMAGEN
    // ============================
    uploadBox.addEventListener('click', function () {
        imageInput.click();
    });

    // ============================
    // ELIMINAR IMAGEN
    // ============================
    document.getElementById('removeImage').addEventListener('click', function () {

        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');

        previewImg.src = '';
        imagePreview.classList.add('d-none');
        uploadPlaceholder.style.display = 'block';

        imageInput.value = '';
        removeImageFlag = true;
    });

    // ============================
    // GUARDAR BRAND
    // ============================
    $('#brandForm').on('submit', function (e) {
        e.preventDefault();

        if (!lock('brandSave')) return;

        let $form = $(this);
        let id = $form.attr('data-id');

        let formData = new FormData(this);

        if (removeImageFlag) {
            formData.append('remove_image', 1);
        }

        let url;
        let method = 'POST';

        // 🔥 DETECTAR SI ES EDIT O CREATE
        if (id) {
            url = `/admin/brands/${id}`;
            formData.append('_method', 'PUT'); // 🔥 CLAVE
        } else {
            url = '/admin/brands';
        }

        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,

            success: function (res) {

                unlock('brandSave');

                $('#brandModal').modal('hide');
                $form.trigger('reset').removeAttr('data-id');

                tableBrand.ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title: res.message || 'Marca guardada',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
            },

            error: function (xhr) {

                unlock('brandSave');

                if (xhr.status === 422) {

                    const errors = xhr.responseJSON.errors;

                    $('.is-invalid').removeClass('is-invalid');
                    $('.invalid-feedback').text('');

                    for (let field in errors) {
                        $('#' + field).addClass('is-invalid');
                        $('#' + field + '-error').text(errors[field][0]);
                    }

                } else {
                    Swal.fire('Error', 'Error al guardar la marca', 'error');
                }
            }
        });

    });

    // ============================
    // EDITAR BRAND
    // ============================
    $(document).on('click', '.editBrand', function () {

        const $btn = $(this);

        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const uploadBox = document.getElementById('uploadBox');
        const imageInput = document.getElementById('image');

        // 🔥 RESET TOTAL
        previewImg.src = '';
        imagePreview.classList.add('d-none');
        uploadPlaceholder.style.display = 'block';
        uploadBox.classList.remove('disabled');
        imageInput.value = '';
        removeImageFlag = false;

        // ============================
        // SET DATA
        // ============================
        $('#brandForm').attr('data-id', $btn.data('id'));
        $('#name').val($btn.data('name'));
        $('#slug').val($btn.data('slug'));
        $('#status').prop('checked', $btn.data('status') == 1);

        const image = $btn.data('image');

        // ============================
        // CARGAR IMAGEN SI EXISTE
        // ============================
        if (image && image !== 'null' && image !== '') {

            previewImg.src = '/storage/' + image;

            imagePreview.classList.remove('d-none');
            uploadPlaceholder.style.display = 'none';

            uploadBox.classList.add('no-upload');
        }

        // ============================
        // ABRIR MODAL
        // ============================
        $('#brandModal').modal('show');
    });

    // ============================
    // RESET MODAL BRAND
    // ============================
    $('#brandModal').on('show.bs.modal', function () {

        const $form = $('#brandForm');

        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const uploadBox = document.getElementById('uploadBox');
        const imageInput = document.getElementById('image');

        if (!$form.attr('data-id')) {

            $form[0].reset();

            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('');

            $('#status').prop('checked', true);
            $('#brandModalLabel').text('Nueva Marca');

            previewImg.src = '';
            imagePreview.classList.add('d-none');
            uploadPlaceholder.style.display = 'block';
            uploadBox.classList.remove('disabled');
            imageInput.value = '';

            removeImageFlag = false;
        }
    });

    // ============================
    // ELIMINAR BRAND
    // ============================
    $(document).on('click', '.deleteBrand', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar marca?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: `/admin/brands/${id}`,
                type: 'DELETE',

                success: function (res) {

                    // 🔥 recargar tabla
                    tableBrand.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Marca eliminada',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                },

                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar la marca', 'error');
                }
            });
        });
    });

});
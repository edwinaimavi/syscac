//OBTENEMOS Y GUARDAMOS EN UNA ELEMENTO HTML CON EL ATERIBUTO ID="DIVLOADING"
let divLoading = document.getElementById('divLoading');
//VARIABLE DE LA TABLA PRODUCTOS
let tableProduct;

//CANDADO PARA ANTI DOBLE CLIC
const submitLocks = {
    productSave: false
};

function lock(action) {
    if (submitLocks[action]) return false;
    submitLocks[action] = true;
    return true;
}

function unlock(action) {
    submitLocks[action] = false
}
document.addEventListener('DOMContentLoaded', function () {


    //CSRF
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    //GENERAMOS EL SLUG
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');

    function generateSlug(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');

    }

    let slugTouched = false;

    slugInput.addEventListener('input', () => slugTouched = true);

    nameInput.addEventListener('input', function () {
        if (!slugTouched || slugInput.value === '') {
            slugInput.value = generateSlug(this.value);
        }
    });
    //FUN DEL SLUG

    //VISTA PREVIA DE LA IMAGEN 
    /* const imageInput = document.getElementById('image');
    const preview = document.getElementById('productPreviewSide');

    imageInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }); */


    //EDITAR PRODUCTO 
    $(document).on('click', '.editProduct', function () {

        const id = $(this).data('id');

        $('#productForm').attr('data-id', id);

        divLoading && (divLoading.style.display = 'flex');

        $.get(`/admin/products/${id}/edit`, function (res) {

            // ============================
            // 🔥 LLENAR PRECIOS
            // ============================
            if (res.prices) {
                for (let typeId in res.prices) {
                    $(`input[name="prices[${typeId}]"]`).val(res.prices[typeId]);
                }
            }

            // ============================
            // DATOS
            // ============================
            $('#name').val(res.name);
            $('#model').val(res.model);
            $('#slug').val(res.slug);
            $('#category_id').val(res.category_id);
            $('#short_description').val(res.short_description);
            tinymce.get('description').setContent(res.description || '');
            $('#price').val(res.price);
            $('#type').val(res.type);

            $('#status').prop('checked', res.status === 'published');
            $('#brand_id').val(res.brand_id);

            // ============================
            // LIMPIAR
            // ============================
            previewContainer.innerHTML = '';
            /* selectedFiles = []; */
            window.imagesToDelete = [];

            // ============================
            // 🔥 MOSTRAR IMÁGENES EXISTENTES
            // ============================
            if (res.images && res.images.length > 0) {

                res.images.forEach(img => {

                    const item = document.createElement('div');
                    item.classList.add('gallery-item');

                    item.innerHTML = `
    <img src="${img.url}">
    <button class="delete-btn" data-id="${img.id}">&times;</button>
`;

                    previewContainer.appendChild(item);
                });
            }

            // ============================
            // ABRIR MODAL
            // ============================
            $('#productModalLabel').text('Editar Producto');
            $('#productModal').modal('show');

        })
            .always(() => {
                divLoading && (divLoading.style.display = 'none');
            });
    });

    // ============================
    // GUARDAR / ACTUALIZAR
    // ============================
    $('#productForm').on('submit', function (e) {
        e.preventDefault();

        if (!lock('productSave')) return;

        tinymce.triggerSave();

        const $form = $(this);
        const $btn = $('#btnSaveProduct');

        $btn.prop('disabled', true).text('Guardando...');
        divLoading && (divLoading.style.display = 'flex');

        const id = $form.attr('data-id');

        let url;
        let formData = new FormData();

        // 👉 agregar inputs normales
        $form.serializeArray().forEach(field => {
            formData.append(field.name, field.value);
        });

        // 👉 agregar imágenes manualmente
        newImages.forEach(file => {
            formData.append('images[]', file);
        });
        // 🔥 enviar imágenes eliminadas
        if (window.imagesToDelete && window.imagesToDelete.length > 0) {
            window.imagesToDelete.forEach(id => {
                formData.append('images_delete[]', id);
            });
        }
        if (id) {
            url = `/admin/products/${id}`;
            formData.append('_method', 'PUT');
        } else {
            url = window.routes.productStore;
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,

            success: function (response) {
                unlock('productSave');
                divLoading && (divLoading.style.display = 'none');

                $('#productModal').modal('hide');
                $form.trigger('reset').removeAttr('data-id');

                // reset imagen preview
                $('#productPreviewSide').attr('src',
                    'https://static.vecteezy.com/system/resources/previews/005/951/722/non_2x/preview-interface-icon-illustration-vector.jpg'
                );

                tableProduct && tableProduct.ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Producto guardado correctamente',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            },

            error: function (xhr) {
                console.log(xhr.responseJSON); // 👈 AGREGA ESTO

                unlock('productSave');
                divLoading && (divLoading.style.display = 'none');

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
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Error al guardar el producto'
                    });
                }
            }
        });
    });

    // ============================
    // LIMPIAR MODAL
    // ============================
    $('#productModal').on('hidden.bs.modal', function () {


        // limpiar precios dinámicos
        $('input[name^="prices"]').val('');
        unlock('productSave');

        $('#productForm')[0].reset();
        $('#productForm').removeAttr('data-id');

        $('#productModalLabel').text('Nuevo Producto');

        $('#status').prop('checked', false);

        $('#productPreviewSide').attr('src',
            'https://static.vecteezy.com/system/resources/previews/005/951/722/non_2x/preview-interface-icon-illustration-vector.jpg'
        );

        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');

        $('#btnSaveProduct')
            .prop('disabled', false)
            .html('<i class="fas fa-save mr-1"></i> Guardar Producto');
        $('#previewContainer').html('');
        newImages = [];
        deletedImages = [];

        $('#previewContainer').html('');
        newImages = [];
        deletedImages = [];
    });

    // ============================
    // VER POST (MODAL)
    // ============================
    $(document).on('click', '.viewProduct', function () {

        const id = $(this).data('id');
        const url = window.routes.productView.replace(':id', id);

        divLoading && (divLoading.style.display = 'flex');

        $.get(url, function (res) {
            console.log(res);
            $('#viewProductName').text(res.name);
            $('#viewProductModel').text(res.model || '—');
            $('#viewProductSlug').text(res.slug);
            $('#viewProductCategory').text(res.category);
            $('#viewProductBrand').text(res.brand || '—');
            $('#viewProductPrice').text(res.price);
            $('#viewProductType').text(res.type);
            $('#viewProductCreatedAt').text(res.created_at);

            $('#viewProductStatus').html(
                res.status === 'published'
                    ? '<span class="badge badge-success">Publicado</span>'
                    : '<span class="badge badge-secondary">Borrador</span>'
            );


            let pricesHtml = '';

            if (res.prices && res.prices.length > 0) {
                res.prices.forEach(p => {
                    pricesHtml += `
            <div class="d-flex justify-content-between border-bottom py-1">
                <span><i class="fas fa-tag mr-1 text-info"></i> ${p.type}</span>
                <strong>${p.price}</strong>
            </div>
        `;
                });
            } else {
                pricesHtml = '<span class="text-muted">Sin precios</span>';
            }

            $('#viewProductPrices').html(pricesHtml);
            // ============================
            // 🔥 GALERÍA PRO
            // ============================

            const mainImage = $('#mainImage');
            const thumbnails = $('#imageThumbnails');

            thumbnails.html(''); // limpiar

            if (res.images && res.images.length > 0) {

                // 👉 imagen principal
                mainImage.attr('src', res.images[0]);

                // 👉 miniaturas
                res.images.forEach((img, index) => {

                    const thumb = `
            <img src="${img}"
                 class="img-thumbnail m-1 thumb-img"
                 style="width:70px; height:70px; object-fit:cover; cursor:pointer;"
                 data-img="${img}">
        `;

                    thumbnails.append(thumb);
                });

            } else {
                mainImage.attr('src', 'https://via.placeholder.com/300x200?text=Sin+Imagen');
            }

            $('#viewProductShort').text(res.short_description || '');
            $('#viewProductDescription').html(res.description);

            // ✅ FIX AQUÍ
            $('#productViewModal').modal('show');

        })
            .fail(() => {
                Swal.fire('Error', 'No se pudo cargar el producto', 'error');
            })
            .always(() => {
                divLoading && (divLoading.style.display = 'none');
            });
    });

    //MOSTRAR TABLA EN LA VISTA
    tableProduct = $('#tableProduct').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.productList,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'type', name: 'type', orderable: false },
            { data: 'price', name: 'price', orderable: false },
            { data: 'status', name: 'status', orderable: false },
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
    // ELIMINAR PRODUCT (SOFT)
    // ============================
    $(document).on('click', '.deleteProduct', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar producto?',
            text: 'El producto se enviará a la papelera',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: `/admin/products/${id}`,
                type: 'DELETE',

                success: function (res) {
                    tableProduct.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Producto eliminado',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                },

                error: function (xhr) {
                    Swal.fire(
                        'Error',
                        xhr.responseJSON?.message || 'No se pudo eliminar el producto',
                        'error'
                    );
                }
            });
        });
    });
    // ============================
    // PREVIEW MULTIPLE IMAGES
    // ============================
    /*    const imagesInput = document.getElementById('images');
       const previewContainer = document.getElementById('previewContainer');
   
       let selectedFiles = [];
   
       if (imagesInput) {
           imagesInput.addEventListener('change', function () {
   
               const files = Array.from(this.files);
   
               files.forEach(file => {
   
                   if (!file.type.startsWith('image/')) return;
   
                   selectedFiles.push(file);
   
                   const reader = new FileReader();
   
                   reader.onload = function (e) {
   
                       const col = document.createElement('div');
                       col.classList.add('col-md-3', 'mb-2');
                       col.innerHTML = `
   <div class="position-relative">
       <img src="${e.target.result}"
            class="img-fluid rounded shadow-sm"
            style="height:120px; object-fit:cover; width:100%;">
   
       <button type="button"
           class="btn btn-danger btn-sm position-absolute btn-remove-image"
           style="top:5px; right:5px;">
           ✕
       </button>
   </div>
   `;
   
   
                       previewContainer.appendChild(col);
                   };
   
                   reader.readAsDataURL(file);
               });
   
           });
       }
    */

    tinymce.init({
        selector: '#description',
        width: "100%",
        height: 400,
        statubar: true,
        plugins: [
            "advlist autolink link image lists charmap print preview hr anchor pagebreak",
            "searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
            "save table contextmenu directionality emoticons template paste textcolor"
        ],
        toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media fullpage | forecolor backcolor emoticons",
    });

});




$(document).on('click', '.delete-btn', function () {

    const id = $(this).data('id');

    // 🔥 SI ES IMAGEN EXISTENTE (BD)
    if (id) {
        window.imagesToDelete.push(id);
    } else {
        // 🔥 SI ES IMAGEN NUEVA
        const index = $(this).closest('.gallery-item').index();
        newImages.splice(index, 1);
    }

    $(this).closest('.gallery-item').remove();

    toggleEmpty();
});

// 🔥 CAMBIAR IMAGEN PRINCIPAL
$(document).on('click', '.thumb-img', function () {
    const src = $(this).data('img');
    $('#mainImage').attr('src', src);
});

// 🔥 eliminar imagen existente (BD)
/* $(document).on('click', '.btn-remove-existing', function () {

    const id = $(this).data('id');

    // guardar para eliminar en backend
    window.imagesToDelete.push(id);

    $(this).closest('.gallery-item').remove();
}); */


//para el modal 


let newImages = [];
let deletedImages = [];

$('#images').on('change', function (e) {
    let files = e.target.files;

    for (let file of files) {

        // evitar duplicados
        if (newImages.some(f => f.name === file.name)) continue;

        newImages.push(file);

        let reader = new FileReader();

        reader.onload = function (e) {

            $('#previewContainer').append(`
                <div class="gallery-item">
                    <img src="${e.target.result}">
                    <button class="delete-btn">&times;</button>
                </div>
            `);

            toggleEmpty();
        };

        reader.readAsDataURL(file);
    }
});

// eliminar visual
$(document).on('click', '.delete-btn', function () {
    $(this).closest('.gallery-item').remove();
    toggleEmpty();
});

// mostrar / ocultar empty
function toggleEmpty() {
    if ($('#previewContainer').children().length > 0) {
        $('#emptyGallery').hide();
    } else {
        $('#emptyGallery').show();
    }
}
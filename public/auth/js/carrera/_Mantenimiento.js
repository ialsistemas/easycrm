var OnSuccessRegistroCarrera, OnFailureRegistroCarrera;
$(function(){

    const $modal = $("#modalMantenimientoCarrera"), $form = $("form#registroCarrera");

    OnSuccessRegistroCarrera = (data) => onSuccessForm(data, $form, $modal);
    OnFailureRegistroCarrera = () => onFailureForm();
});

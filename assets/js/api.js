import Noty from 'noty';
import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';

Noty.overrideDefaults({
    timeout: 5000
});

/**
* Represents a default collection of request for common actions through the app.
* 
* @param object A string representing the concerned collection. Typically the
* common suffix of concerned routes name.
*/
export default class API {
    constructor(object) {
        this.collection = object;
    }

    edit(id) {
        const url = Routing.generate('edit_' + this.collection, {
            id: id
        })
        window.location.href = url;
    }
    
    toggle(id) {
        $.ajax({
            url: Routing.generate('toggle_' + this.collection, {
                id: id
            }),
            method: 'PATCH',
            contentType: 'application/json'
        })
        .done(function (data, status) {
            $('table.dataTable').DataTable().ajax.reload();
            
            new Noty({
                type: 'success',
                text: data.message
            }).show();
        })
        .fail(function (data, status) {
            new Noty({
                type: 'error',
                text: data.responseJSON.message
            }).show();
        });
    }
    
    delete(id) {
        $.ajax({
            url: Routing.generate('delete_' + this.collection, {
                id: id
            }),
            method: 'DELETE',
            contentType: 'application/json'
        })
        .done(function (data, status) {
            $('table.dataTable').DataTable().ajax.reload();
            
            new Noty({
                type: 'success',
                text: data.message
            }).show();
        })
        .fail(function (data, status) {
            new Noty({
                type: 'error',
                text: data.responseJSON.message
            }).show();
        });
    }
};
/* eslint-disable no-console */
import Noty from 'noty';
import Axios from 'axios';

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

    edit(url) {
        window.location.href = url;
    }

    toggle(url) {
        // const api = API.getInstance();

        $.ajax({
            url,
            method: 'PATCH',
            contentType: 'application/json'
        })
        .done(function (data) {
            $('table.dataTable').DataTable().ajax.reload();

            new Noty({
                type: 'success',
                text: data.message
            }).show();
        })
        .fail(function (data) {
            new Noty({
                type: 'error',
                text: data.responseJSON.message
            }).show();
        });
    }

    delete(url) {
        API.getInstance().delete(url)
        .then(() => {
            $('table.dataTable').DataTable().ajax.reload();

            new Noty({
                type: 'success',
                text: "Item has been deleted."
            }).show();
        })
        // $.ajax({
        //     url,
        //     method: 'DELETE',
        //     dataType: 'text'
        // })
        // .done(function (data) {
        //     $('table.dataTable').DataTable().ajax.reload();

        //     new Noty({
        //         type: 'success',
        //         text: "Item has been deleted."
        //     }).show();
        // })
        // .fail(function (data) {
        //     new Noty({
        //         type: 'error',
        //         text: "Error while deleting this item."
        //     }).show();
        // });
    }
}

API.getInstance = (options = {}) => {
    const axios = Axios.create(options);

    axios.interceptors.request.use(
        config => {
            options.beforeSend && options.beforeSend();
            return config;
        }, error => {
            return Promise.reject(error);
        }
    );

    // Add a response interceptor
    axios.interceptors.response.use(
        response => {
            options.responseCallback && options.responseCallback();
            options.successCallback && options.successCallback();
            return response;
        },
        /** @param {import('axios').AxiosError} error */
        error => {
            options.responseCallback && options.responseCallback();
            options.errorCallback && options.errorCallback();
            // Unauthentified
            console.error(error.config.url + " (" + error.response.status + ") " + error.response.statusText);
            if (error.response.status === 401) {
                new Noty({
                    type: 'error',
                    text: 'Your session has expired. Please log in again.'
                }).show();

                window.location.href = '/login?ref_url=' + encodeURIComponent(window.location.href);
            } else if (error.response.status >= 500) {
                // new Noty({
                //     type: 'error',
                //     text: 'Oops, an error happened. Please reload your window.'
                // }).show();
            }

            return Promise.reject(error);
        }
    );

    return axios;
}
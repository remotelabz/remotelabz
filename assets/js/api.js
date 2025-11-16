/* eslint-disable no-console */
import { ToastContainer, toast } from 'react-toastify';
import Axios from 'axios';

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

            toast.success(data.message, {
            });
        })
        .fail(function (data) {

            toast.error(data.responseJSON.message, {
                    autoClose: 10000,
                });
        });
    }

    delete(url) {
        return API.getInstance().delete(url)
        .then(() => {
            $('table.dataTable').DataTable().ajax.reload();

            toast.success("Item has been deleted.", {
            });
        });
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
                
                toast.error('Your session has expired. Please log in again.', {
                    autoClose: 10000,
                });

                window.location.href = '/login?ref_url=' + encodeURIComponent(window.location.href);
            } else if (error.response.status >= 500) {
                toast.error('Oops, an error happened. Please reload your window.', {
                    autoClose: 10000,
                });
            }

            return Promise.reject(error);
        }
    );

    return axios;
}
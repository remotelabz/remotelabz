"use strict";

import Noty from 'noty';
import Axios from 'axios';

/**
 * @typedef {Object} Lab
 * @property {number} id
 * @property {string} name
 * @property {string} description
 * @property {Device[]} devices
 * @property {Instance[]} instances
 * @property {{id: number}} author
 * @property {string} uuid
 * @property {string} createdAt
 * @property {string} lastUpdated
 * 
 * @typedef {Object} Device
 * @property {number} id
 * @property {string} name
 * @property {string} brand
 * @property {string} model
 * @property {number} launchOrder
 * @property {NetworkInterface[]} networkInterfaces
 * @property {string} type
 * @property {number} virtuality
 * @property {Hypervisor} [hypervisor]
 * @property {OperatingSystem} operatingSystem
 * @property {Flavor} flavor
 * @property {string} uuid
 * @property {string} createdAt
 * @property {string} lastUpdated
 * @property {EditorData} editorData
 * @property {boolean} isTemplate
 * 
 * @typedef {Object} NetworkInterface
 * @property {number} id
 * @property {NetworkInterfaceType} type
 * @property {string} name
 * @property {{name: string}} device
 * @property {string} macAddress
 * @property {NetworkInterfaceAccess} accessType
 * @property {string} uuid
 * 
 * @typedef {Object} NetworkInterfaceOptions
 * @property {string} name
 * @property {boolean} [isTemplate]
 * @property {NetworkInterfaceAccess} [accessType]
 * 
 * @typedef {Object} OperatingSystem
 * @property {number} id
 * @property {string} name
 * @property {string} image URL form of the image used to create instances.
 * 
 * @typedef {Object} Flavor
 * @property {number} id
 * @property {string} name
 * @property {number} memory Amount of RAM in MB.
 * @property {number} disk
 * 
 * @typedef {Object} EditorData
 * @property {number} id
 * @property {number} x
 * @property {number} y
 * 
 * @typedef {Object} DeviceInstance
 * @property {InstanceOwnerInterface} owner
 * @property {string} uuid
 * @property {InstanceOwnerType} ownedBy
 * @property {Device} device
 * @property {InstanceStateType} state
 * 
 * @typedef {Object} LabInstance
 * @property {InstanceOwnerInterface} owner
 * @property {string} uuid
 * @property {InstanceOwnerType} ownedBy
 * @property {{name: string, uuid: string}} lab
 * @property {DeviceInstance[]} deviceInstances
 * 
 * @typedef {Object} User
 * @property {number} id
 * @property {string} email
 * @property {string} lastName
 * @property {string} firstName
 * @property {string} name Full name.
 * @property {boolean} enabled If user is able to connect or not.
 * @property {string} createdAt Datetime format.
 * @property {string} lastActivity Datetime format.
 * @property {string} uuid
 * @property {string[]} roles
 * 
 * @typedef {Object} Group
 * @property {number} id
 * @property {string} path
 * @property {string} name
 * @property {string} slug
 * @property {string} description
 * @property {string} createdAt Datetime format.
 * @property {string} updatedAt Datetime format.
 * @property {string} uuid
 * 
 * @typedef {"stopped"|"starting"|"started"|"stopping"|"error"} InstanceStateType
 * @typedef {"lab"|"device"} InstanceType
 * @typedef {"user"|"group"} InstanceOwnerType
 * @typedef {{name: string, uuid: string}} InstanceOwnerInterface
 * @typedef {"qemu"} Hypervisor
 * @typedef {"tap"} NetworkInterfaceType
 * @typedef {"VNC"|null} NetworkInterfaceAccess
 */

Noty.overrideDefaults({
    timeout: 5000
});

let options = {};
options.baseURL = '/api';

const axios = Axios.create(options);

// Add a response interceptor
axios.interceptors.response.use(
    (response) => response,
    /** @param {import('axios').AxiosError} error */
    (error) => {
        // Unauthentified
        console.error(error);
        if (error.response.status === 401) {
            window.location.href = '/login?ref_url=' + encodeURIComponent(window.location.href);
        } else if (error.response.status >= 500) {
            // TODO
        }

        return Promise.reject(error);
    }
);

export class RemotelabzAPI {
    /**
     * User endpoint.
     */
    users = {
        /**
         * Get a collection of users.
         * 
         * Implements GET `/api/users`
         * 
         * @param {number} search Search string. Can contain anything in user's name or email.
         * @param {number} limit Limit number of users fetched.
         * 
         * @returns {Promise<import('axios').AxiosResponse<User[]>>}
         */
        all(search = '', limit = 10) {
            return axios.get('/users', {
                params: {
                    limit,
                    search
                }
            })
        },
    }

    /**
     * Group endpoint.
     */
    groups = {
        /**
         * Get a collection of groups.
         * 
         * Implements GET `/api/groups`
         * 
         * @param {number} search Search string. Can contain anything in user's name or email.
         * @param {number} limit Limit number of groups fetched.
         * 
         * @returns {Promise<import('axios').AxiosResponse<Group[]>>}
         */
        all(search = '', limit = 10, page = 1, withUsers = true) {
            return axios.get('/groups', {
                params: {
                    limit,
                    search,
                    page,
                    context: withUsers ? ["group_users", "groups"] : "groups"
                }
            })
        },
    }

    /**
     * Labs endpoint.
     */
    labs = {
        /**
         * Get a collection of labs.
         * 
         * Implements GET `/api/labs`
         * 
         * @param {number} limit Limit number of labs fetched.
         * 
         * @returns {Promise<import('axios').AxiosResponse<Lab[]>>}
         */
        all(limit = 10) {
            return axios.get('/labs', {
                params: {
                    limit
                }
            })
        },

        /**
         * Get a lab by ID.
         * 
         * Implements GET `/api/labs/{id}`
         * 
         * @param {number} id 
         * 
         * @returns {Promise<import('axios').AxiosResponse<Lab>>}
         */
        get(id) {
            return axios.get(`/labs/${id}`);
        }
    }

    /**
     * Network interfaces endpoint.
     */
    networkInterfaces = {
        /**
         * Get a network interface by ID.
         * 
         * Implements GET `/api/network-interfaces/{id}`
         * 
         * @param {number} id 
         * 
         * @returns {Promise<import('axios').AxiosResponse<NetworkInterface>>}
         */
        get(id) {
            return axios.get(`/network-interfaces/${id}`);
        },

        /**
         * Create a new network interface.
         * 
         * Implements POST `/api/network-interfaces`
         * 
         * @param {NetworkInterfaceOptions} options 
         * 
         * @returns {Promise<import('axios').AxiosResponse<NetworkInterface>>}
         */
        create(options = {}) {
            /** @type {NetworkInterfaceOptions} */
            const data = {
                name: 'New network interface',
                isTemplate: false
            };

            Object.assign(data, options);

            return axios.post('/network-interfaces', data);
        },

        /**
         * Update a network interface.
         * 
         * Implements PUT `/api/network-interfaces/{id}`
         * 
         * @param {number} id 
         * @param {NetworkInterfaceOptions} options 
         * 
         * @returns {Promise<import('axios').AxiosResponse<NetworkInterface>>}
         */
        update(id, options = {}) {
            return axios.put(`/network-interfaces/${id}`, options);
        },

        /**
         * Delete a network interface.
         * 
         * Implements DELETE `/api/network-interfaces/{id}`
         * 
         * @param {number} id 
         * 
         * @returns {Promise<import('axios').AxiosResponse<null>>}
         */
        delete(id) {
            return axios.delete(`/network-interfaces/${id}`);
        },
    }

    /**
     * Instances endpoint.
     */
    instances = {
        /**
         * Get an instance by UUID.
         * 
         * Implements GET `/api/instances/by-uuid/{uuid}`
         * 
         * @param {string} uuid 
         * @param {InstanceType} type
         * 
         * @returns {Promise<import('axios').AxiosResponse<LabInstance|DeviceInstance>>}
         */
        get(uuid, type) {
            return axios.get(`/instances/by-uuid/${uuid}`, { params: { type } });
        },
        /**
         * Lab instances methods
         */
        lab: {
            /**
             * Get a lab instance by UUID.
             * 
             * Implements GET `/api/instances/by-uuid/{uuid}`
             * 
             * @param {string} uuid 
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            get(uuid) {
                return axios.get(`/instances/${uuid}`, { params: { type: 'lab' } });
            },

            /**
             * Get a lab instance by lab and user UUID.
             * 
             * Implements GET `/api/instances/lab/{labUuid}/by-user/{userUuid}`
             * 
             * @param {string} labUuid
             * @param {string} userUuid
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getByLabAndUser(labUuid, userUuid) {
                return axios.get(`/instances/lab/${labUuid}/by-user/${userUuid}`);
            },

            /**
             * Get a lab instance by lab and group UUID.
             * 
             * Implements GET `/api/instances/lab/{labUuid}/by-group/{groupUuid}`
             * 
             * @param {string} labUuid
             * @param {string} groupUuid
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getByLabAndGroup(labUuid, groupUuid) {
                return axios.get(`/instances/lab/${labUuid}/by-group/${groupUuid}`);
            },

            /**
             * Delete a lab instance by UUID.
             * 
             * Implements DELETE `/api/instances/{uuid}`
             * 
             * @param {string} uuid 
             * 
             * @returns {Promise<import('axios').AxiosResponse<void>>}
             */
            delete(uuid) {
                return axios.delete(`/instances/${uuid}`);
            }
        },

        /**
         * Device instances methods
         */
        device: {
            /**
             * Get a device instance by UUID.
             * 
             * Implements GET `/api/instances/by-uuid/{uuid}`
             * 
             * @param {string} uuid 
             * 
             * @returns {Promise<import('axios').AxiosResponse<DeviceInstance>>}
             */
            get(uuid) {
                return axios.get(`/instances/${uuid}`, { params: { type: 'device' } });
            }
        },
    }

    jitsiCall = {
        /**
         * Start a Call in lab instance by UUID.
         * 
         * Implements GET `/api/jitsi-call/{labUuid}/{groupUuid}/start
         * 
         * @param {string} labUuid
         * @param {string} groupUuid
         * 
         * @return {Promise<import('axios').AxiosResponse<void>>}
         */
        start(labUuid, groupUuid) {
            return axios.get(`/jitsi-call/${labUuid}/${groupUuid}/start`);
        },

        /**
         * Join a Call in lab instance by UUID and group UUID
         * 
         * Implements GET `/api/jitsi-call/{labUuid}/{groupUuid}/join
         * 
         * @param {string} labUuid
         * @param {string} groupUuid
         * 
         * @return {Promise<import('axios').AxiosResponse<void>>}
         */
        join(labUuid, groupUuid) {
            return axios.get(`/jitsi-call/${labUuid}/${groupUuid}/join`);
        }
    }
}

const Remotelabz = new RemotelabzAPI();
export default Remotelabz;
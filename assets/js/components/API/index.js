"use strict";

import Noty from 'noty';
import Axios from 'axios';
const url = require('url');

/**
 * @typedef {Object} Lab
 * @property {number} id
 * @property {string} name
 * @property {string} shortDescription
 * @property {string} description
 * @property {[{id: number}]} devices
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
 * @property {[{id: number}]} networkInterfaces
 * @property {[{id: number}]} labs
 * @property {string} type
 * @property {number} virtuality
 * @property {string} hypervisor
 * @property {{id: number}} operatingSystem
 * @property {{id: number}} flavor
 * @property {string} uuid
 * @property {string} createdAt
 * @property {string|null} lastUpdated
 * @property {EditorData} editorData
 * @property {boolean} isTemplate
 * @property {boolean} vnc
 * 
 * @typedef {Object} Network
 * @property {{addr: string}} ip
 * @property {{addr: string}} netmask
 * 
 * @typedef {Object} NetworkInterface
 * @property {number} id
 * @property {NetworkInterfaceType} type
 * @property {string} name
 * @property {{id: number}} device
 * @property {string} uuid
 * @property {number} vlan
 * @property {boolean} isTemplate
 * 
 * @typedef {Object} ControlProtocol
 * @property {number} id
 * @property {string} name
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
 * @typedef {Object} Hypervisor
 * @property {number} id
 * @property {string} name
 * 
 * @typedef {Object} Flavor
 * @property {number} id
 * @property {string} name
 * @property {number} memory Amount of RAM in MB.
 * @property {number} disk Amount of disk space in GB.
 * 
 * @typedef {Object} EditorData
 * @property {number} id
 * @property {number} x
 * @property {number} y
 * 
 * @typedef {Object} InstanceOwner
 * @property {number} id
 * @property {string} uuid
 * 
 * @typedef {Object} Instance
 * @property {number} id
 * @property {string} uuid
 * @property {InstanceOwner} owner
 * @property {InstanceOwnerType} ownedBy
 * 
 * @typedef {Object} DeviceInstance
 * @property {string} uuid
 * @property {InstanceOwner} owner
 * @property {number} id
 * @property {InstanceOwnerType} ownedBy
 * @property {{id: number}} device
 * @property {Instance} labInstance
 * @property {[{id: number, ownedBy: InstanceOwnerType, uuid: string, owner: InstanceOwner, networkInterface: {id: number}, macAddress: string}]} networkInterfaceInstances
 * @property {InstanceStateType} state
 * 
 * @typedef {Object} LabInstance
 * @property {InstanceOwner} owner
 * @property {string} uuid
 * @property {InstanceOwnerType} ownedBy
 * @property {string} bridgeName
 * @property {number} id
 * @property {{id: number}} lab
 * @property {Instance[]} deviceInstances
 * @property {boolean} isInterconnected
 * @property {boolean} isInternetConnected
 * @property {Network} network
 * @property {InstanceStateType} state
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
 * @property {boolean} isShibbolethUser 
 * @property {Role[]} roles
 * @property {[{id: number, name: string, slug: string, uuid: string, role: UserGroupRole}]} groups
 * 
 * @typedef {Object} Group
 * @property {number} id
 * @property {string} path
 * @property {string} name
 * @property {string} slug
 * @property {string} description
 * @property {string} createdAt Datetime format.
 * @property {string} updatedAt Datetime format.
 * @property {number} visibility
 * @property {string} uuid
 * @property {{id: number, name: string, role: UserGroupRole}} users
 * @property {Group[]} children
 * 
 * @typedef {Object} InvitationCode
 * @property {number} id
 * @property {string} mail
 * @property {string} code
 * @property {string} expiryDate Datetime format.
 * @property {{id: number, name: string}} lab
 * 
 * @typedef {Object} ConfigWorker
 * @property {number} id
 * @property {string} IPv4
 * @property {string} queueName
 * @property {boolean} available
 * 
 * @typedef {"stopped"|"starting"|"started"|"stopping"|"error"} InstanceStateType
 * @typedef {"lab"|"device"} InstanceType
 * @typedef {"user"|"group"} InstanceOwnerType
 * @typedef {{name: string, uuid: string}} InstanceOwnerInterface
 * @typedef {"qemu"|"lxc"} Hypervisor
 * @typedef {"tap"} NetworkInterfaceType
 * @typedef {"VNC"|null} NetworkInterfaceAccess
 * @typedef {"ROLE_USER"|"ROLE_TEACHER"|"ROLE_TEACHER_EDITOR"|"ROLE_ADMINISTRATOR"|"ROLE_SUPER_ADMINISTRATOR"} Role
 * @typedef {"user"|"admin"|"owner"} UserGroupRole
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
        switch (error.response.status) {
            case 401:
                window.location.href = '/login?ref_url=' + encodeURIComponent(window.location.href);
                break;
            case 404:
            default:
                console.error(error);
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

        /**
         * Get a collection of users.
         * 
         * Implements GET `/api/fetch/users`

         * @returns {Promise<import('axios').AxiosResponse<User[]>>}
         */
        fetchAll() {
            return axios.get('/fetch/users')
        },

        /**
         * Get a collection of users in group of $user.
         * 
         * Implements GET `/api/fetch/{userType}/by-group-owner/{id}`
         * 
         * @param {number} id ID of the user
         * @param {string} userType type of the users to search
         * 
         * @returns {Promise<import('axios').AxiosResponse<User[]>>}
         */
        fetchUserTypeByGroupOwner(userType, id) {
            return axios.get(`/fetch/${userType}/by-group-owner/${id}`)
        },

        /**
         * Get an user by its ID.
         * 
         * Implements GET `/api/users/{id}`
         * 
         * @param {number} id ID of the user
         * 
         * @returns {Promise<import('axios').AxiosResponse<User>>}
         */
        get(id) {
            return axios.get(`/users/${id}`);
        }
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
        all(search = '', limit = 10, page = 1, rootOnly = true) {
            return axios.get('/groups', {
                params: {
                    limit,
                    search,
                    page,
                    root_only: rootOnly ? 1 : 0
                }
            })
        },

        /**
         * Get a group by its path.
         * 
         * Implements GET `/api/groups/{path}`
         * 
         * @param {string} path Complete path of the device
         * 
         * @returns {Promise<import('axios').AxiosResponse<Group>>}
         */
        get(path) {
            return axios.get(`/groups/${path}`);
        }
    }

    /**
     * Device endpoint.
     */
    devices = {
        /**
         * Get a collection of devices.
         * 
         * Implements GET `/api/devices/{id}`
         * 
         * @param {number} id ID of the device
         * 
         * @returns {Promise<import('axios').AxiosResponse<Device>>}
         */
        get(id) {
            return axios.get(`/devices/${id}`);
        },

        /**
         * Create a new device.
         * 
         * Implements POST `/api/devices`
         * 
         * @param {Device} options Fields to set and their values
         * 
         * @returns {Promise<import('axios').AxiosResponse<Device>>}
         */
        create(options) {
            return axios.post('/devices', options)
        },

        /**
         * Updates a device by ID.
         * 
         * Implements PUT `/api/devices/{id}`
         * 
         * @param {number} id ID of the device to update
         * @param {Device} options Fields to update and their values
         * 
         * @returns {Promise<import('axios').AxiosResponse<Device>>}
         */
        update(id, options) {
            return axios.put(`/devices/${id}`, options)
        },

        /**
         * Updates a device position in editor by ID.
         * 
         * Implements PUT `/api/devices/{id}/editor-data`
         * 
         * @param {number} id
         * @param {number} x 
         * @param {number} y 
         * 
         * @returns {Promise<import('axios').AxiosResponse<{x: number, y: number}>>}
         */
        updatePosition(id, x, y) {
            return axios.put(`/devices/${id}/editor-data`, {x, y})
        },

        /**
         * Delete a device by ID.
         * 
         * Implements DELETE `/api/devices/{id}`
         * 
         * @param {number} id ID of the device to delete
         * 
         * @returns {Promise<import('axios').AxiosResponse<null>>}
         */
        delete(id) {
            return axios.delete(`/devices/${id}`)
        },

        /**
         * Request an async network interface by device id
         * 
         * Implements GET `/api/device/{id<\d+>}/networkinterface`
         * 
         * @param {integer} id
         * 
         * @returns {Promise<import('axios').AxiosResponse<void>>}
         */
        getNbNetworkInterface(id) {
            return axios.get(`/device/${id}/networkinterface`);
        }
    }

    /**
     * Labs endpoint.
     */
    labs = {
        /**
         * Get a collection of labs.
         * 
         * Implements GET `/api/labs`
         * @param {string} search Search string. Can contain anything in lab's name.
         * @param {number} limit Limit number of groups fetched.
         * 
         * @returns {Promise<import('axios').AxiosResponse<Lab[]>>}
         */
        all(search = '', limit = 10) {
            return axios.get('/labs', {
                params: {
                    limit,
                    search
                }
            })
        },

        /**
         * Get a collection of labs by teacher ID.
         * 
         * Implements GET `/api/labs/teacher/{id}`
         * 
         * @returns {Promise<import('axios').AxiosResponse<Lab[]>>}
         */
        getByTeacher(id) {
            return axios.get(`/labs/teacher/${id}`)
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
        },

        /**
         * Get a labs template.
         * 
         * Implements GET `/api/labs/template/{id}`
         * 
         *  @param {number} id  
         * 
         * @returns {Promise<import('axios').AxiosResponse<Lab>>}
         */
        getTemplate(id) {
            return axios.get(`/labs/template/${id}`);
        },

        /**
         * Get a labs template.
         * 
         * Implements GET `/api/labs/template`
         *  
         * 
         * @returns {Promise<import('axios').AxiosResponse<Lab>>}
         */
        getTemplates() {
            return axios.get(`/labs/template`);
        },

        /**
         * Updates a lab by ID.
         * 
         * Implements PUT `/api/labs/{id}`
         * 
         * @typedef {Object} UpdateLabParams
         * @property {number} id ID of the lab to update
         * @property {Object} fields Fields to update and their values
         * 
         * @param {UpdateLabParams} params 
         * 
         * @returns {Promise<import('axios').AxiosResponse<Lab>>}
         */
        update(params) {
            return axios.put(`/labs/${params.id}`, params.fields)
        },

        /**
         * Updates a lab from a JSON string.
         * 
         * Implements POST `/api/labs/import`
         * 
         * @param {string} json 
         * 
         * @returns {Promise<import('axios').AxiosResponse<void>>}
         */
        import(json) {
            return axios.post(`/labs/import`, { json });
        },

        /**
         * copy a banner by lab ID.
         * 
         * Implements GET `/api/labs/{id}/banner/{newId}`
         * 
         * @param {number} id 
         * @param {number} newId 
         * 
         * @returns {Promise<import('axios').AxiosResponse<{url: string}>>}
         */
        copyBanner(id, newId) {
            return axios.get(`/labs/${id}/banner/${newId}`);
        },

        /**
         * Updates a banner for a lab by ID.
         * 
         * Implements PUT `/api/labs/{id}/banner`
         * 
         * @param {number} id 
         * @param {File} image 
         * 
         * @returns {Promise<import('axios').AxiosResponse<{url: string}>>}
         */
        uploadBanner(id, image) {
            var formData = new FormData();
            formData.append("banner", image);
            return axios.post(`/labs/${id}/banner`, formData, { headers : { 'Content-Type': 'multipart/form-data' } });
        },

        /**
         * 
         * Add device in the lab ID
         * 
         * Implements POST `/api/labs/{id<\d+>}/devices`
         * @param {int} id
         * @param {device} options
         * @returns 
         */

        addDeviceInLab(id,options) {
            return axios.post(`/labs/${id}/devices`,options);
        },

        /**
         * 
         * Add device in the lab ID
         * 
         * Implements POST `/api/labs/{id<\d+>}`
         * @param {int} id
         * @returns 
         */

        delete(id) {
            return axios.delete(`/labs/${id}`);
        }
        
    }

    /**
     * InvitationCode endpoint.
     */
    invitationCode = {
        /**
         * Get a invitation code by lab ID.
         * 
         * Implements GET `/api/codes/by-lab/{id}`
         * 
         * @param {number} id 
         * 
         * @returns {Promise<import('axios').AxiosResponse<InvitationCode>>}
         */
        getByLab(id) {
            return axios.get(`/codes/by-lab/${id}`);
        },
        /**
         * delete an invitation code by lab UUID.
         * 
         * Implements DELETE `/api/codes/{uuid}`
         * 
         * @param {number} uuid 
         * 
         * @returns {Promise<import('axios').AxiosResponse<void>>}
         */
        delete(uuid) {
            return axios.delete(`/codes/${uuid}`);
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
                name: options.name,
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
         * 
         * @returns {Promise<import('axios').AxiosResponse<LabInstance|DeviceInstance>>}
         */
        get(uuid) {
            return axios.get(`/instances/by-uuid/${uuid}`);
        },

        /**
             * Request an async instance by UUID.
             * 
             * Implements GET `/api/instances/export/by-uuid/{uuid}`
             * 
             * @param {string} uuid
             * @param {string} name
             * @param {string} type
             * 
             * @returns {Promise<import('axios').AxiosResponse<void>>}
             */
        export(uuid,new_name,type) {
            return axios.get(`/instances/export/by-uuid/${uuid}`,{ params: { name: new_name, type: type}});
        },

        /**
         * Lab instances methods
         */
        lab: {
            /**
             * Create a lab instance.
             * 
             * Implements POST `/api/instances/create`
             * 
             * @param {string} labUuid 
             * @param {string} instancierUuid 
             * @param {"user"|"group"} instancierType 
             * @param {true|false} boolean // From export or not
             * @returns {Promise<import('axios').AxiosResponse<void>>}
             */
            create(lab, instancier, instancierType, fromexport) {
                var myDataObj = { lab, instancier, instancierType, fromexport };
                var formData = new FormData();

                for (var key in myDataObj) {
                    formData.append(key, myDataObj[key])
                }

                // return axios.post(`/instances/create`, formData, {
                //     headers: { 'Content-Type': 'multipart/form-data' }
                // });
                return axios.post(`/instances/create`, myDataObj);
            },

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
                return axios.get(`/instances/by-uuid/${uuid}`, { params: { type: 'lab' } });
            },

            /**
             * Get all lab instance.
             * 
             * Implements GET `/api/instances`
             * 
             * @returns {Promise<import('axios').AxiosResponse<Array<LabInstance>>>}
             */
            getAll(filter="none", subFilter="allInstances", page=1) {
                return axios.get(`/instances`, {params: {"instance[filter]": filter, "instance[subFilter]": subFilter, page}});
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
             * Get a lab instance by lab and user UUID.
             * 
             * Implements GET `/api/instances/lab/{labUuid}/by-guest/{guestUuid}`
             * 
             * @param {string} labUuid
             * @param {string} guestUuid
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getByLabAndGuest(labUuid, guestUuid) {
                return axios.get(`/instances/lab/${labUuid}/by-guest/${guestUuid}`);
            },

            /**
             * Get lab instances by user UUID.
             * 
             * Implements GET `/api/instances/lab/by-user/{userUuid}`
             * 
             * @param {string} userUuid
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getByUser(userUuid) {
                return axios.get(`/instances/lab/by-user/${userUuid}`);
            },

            /**
             * Get lab instances owned by user type.
             * 
             * Implements GET `/api/instances/lab/owned-by-user-type/{userType}`
             * 
             * @param {string} userType
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getOwnedByUserType(userType) {
                return axios.get(`/instances/lab/owned-by-user-type/${userType}`);
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
             * Get lab instances by group UUID.
             * 
             * Implements GET `/api/instances/lab/by-group/{groupUuid}`
             * 
             * @param {string} groupUuid
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getByGroup(groupUuid) {
                return axios.get(`/instances/lab/by-group/${groupUuid}`);
            },

            /**
             * Get lab instances of group.
             * 
             * Implements GET `/api/groups/{slug}/instances`
             * 
             * @param {string} slug
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getGroupInstances(slug, filter="allLabs", page=1) {
                return axios.get(`/groups/${slug}/instances`, {params: {"group_instance[filter]": filter, page}});
            },

            /**
             * Get lab instances of group by lab.
             * 
             * Implements GET `/api/groups/{slug}/lab/{uuid}/labInstances`
             * 
             * @param {string} slug
             * @param {string} uuid
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getGroupInstancesByLab(slug, uuid) {
                return axios.get(`/groups/${slug}/lab/${uuid}/labInstances`);
            },

            /**
             * Get lab instances of user group.
             * 
             * Implements GET `/api/instances/lab/by-group`
             * 
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getByGroups() {
                return axios.get(`/instances/lab/by-group`);
            },

            /**
             * Get lab instances by lab UUID.
             * 
             * Implements GET `/api/instances/lab/by-lab/{labUuid}`
             * 
             * @param {string} labUuid
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getByLab(labUuid) {
                return axios.get(`/instances/lab/by-lab/${labUuid}`);
            },

            /**
             * Get lab instances by ordered by lab.
             * 
             * Implements GET `/api/instances/lab/ordered-by-lab`
             * 
             * 
             * @returns {Promise<import('axios').AxiosResponse<LabInstance>>}
             */
            getOrderedByLab() {
                return axios.get(`/instances/lab/ordered-by-lab`);
            },

            /**
             * Delete a lab instance by UUID.
             * 
             * Implements DELETE `/api/instances`
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
            },

            /**
             * Request an async device instance start by UUID.
             * 
             * Implements GET `/api/instances/start/by-uuid/{uuid}`
             * 
             * @param {string} uuid 
             * 
             * @returns {Promise<import('axios').AxiosResponse<void>>}
             */
            start(uuid) {
                return axios.get(`/instances/start/by-uuid/${uuid}`);
            },

            /**
             * Request an async device instance stop by UUID.
             * 
             * Implements GET `/api/instances/stop/by-uuid/{uuid}`
             * 
             * @param {string} uuid 
             * 
             * @returns {Promise<import('axios').AxiosResponse<void>>}
             */
            stop(uuid) {
                return axios.get(`/instances/stop/by-uuid/${uuid}`);
            },

            /**
             * Request an async device instance reset by UUID.
             * 
             * Implements GET `/api/instances/reset/by-uuid/{uuid}`
             * 
             * @param {string} uuid 
             * 
             * @returns {Promise<import('axios').AxiosResponse<void>>}
             */
            reset(uuid) {
                return axios.get(`/instances/reset/by-uuid/${uuid}`);
            },

            logs(uuid) {
                return axios.get(`/instances/${uuid}/logs`);
            }
        },
    }

    /**
     * JitsiCall endpoint.
     */
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

    /**
     * configWorker endpoint.
     */
    configWorker = {
        /**
         * Get a collection of workers.
         * 
         * Implements GET `/api/config/workers`
         * 
         * 
         * @returns {Promise<import('axios').AxiosResponse<ConfigWorker[]>>}
         */
        all() {
            return axios.get('/config/workers')
        },
        /**
             * Add a worker.
             * 
             * Implements POST `/api/config/worker/new`
             * 
             * @param {ConfigWorker} options Fields to set and their values
            * 
            * @returns {Promise<import('axios').AxiosResponse<ConfigWorker>>}
             */
        new(options) {
            return axios.post(`/config/worker/new`, options);
        },
         /**
         * Updates a worker.
         * 
         * Implements PUT `/api/config/worker/{id}`
         * 
         * @param {number} id ID of the device to update
         * @param {ConfigWorker} options Fields to set and their values
         * 
         * @returns {Promise<import('axios').AxiosResponse<ConfigWorker>>}
         */
         update(id, options) {
            return axios.put(`/config/worker/${id}`, options)
        },
        /**
         * Delete a worker.
         * 
         * Implements DELETE `/api/config/worker/{id}`
         * 
         * @param {number} id ID of the device to update
         * @returns {Promise<import('axios').AxiosResponse<ConfigWorker>>}
         */
        delete(id) {
            return axios.delete(`/config/worker/${id}`)
        }
    }

    /**
     * textObject endpoint.
     */
    textObjects = {
        /**
         * Start a Call in lab instance by UUID.
         * 
         * Implements POST `/api/labs/{labid}/textobjects
         * 
         * @typedef {Object} newTextObjectParams
         * @property {number} id ID of the lab of the textobject
         * @property {Object} fields Fields of textobject
         * 
         * @param {newTextObjectParams} params 
         * 
         * @return {Promise<import('axios').AxiosResponse<void>>}
         */
        new(params) {
            return axios.post(`/labs/${params.labid}/textobjects`, params.fields);
        }
    }

    /**
     * picture endpoint.
     */
    pictures = {
        /**
         * Start a Call in lab instance by UUID.
         * 
         * Implements POST `/api/labs/{labid}/pictures
         * 
         * @typedef {Object} newPictureParams
         * @property {number} id ID of the lab of the picture
         * @property {Object} fields Fields of picture
         * 
         * @param {newPictureParams} params 
         * 
         * @return {Promise<import('axios').AxiosResponse<void>>}
         */
        new(params) {
            return axios.post(`/labs/${params.labid}/pictures`, params.fields);
        }
    }
}

const Remotelabz = new RemotelabzAPI();
export default Remotelabz;
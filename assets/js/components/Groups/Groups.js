import { Group } from '.';

/**
 * @enum {string}
 */
export const GroupRoles = {
    Owner: 'owner',
    Admin: 'admin',
    Member: 'member'
};

/**
 * @enum {string}
 */
export const GroupRoleLabel = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member'
};

/**
 * Return CSS class for group's identicon
 * 
 * @param {Group} group
 */
export const getGroupIdenticonClass = (group) => {
    return `bg-${group.id % 8 + 1}`;
}

/**
 * @param {Group} group 
 */
export const getGroupPath = (group) => {
    let path = '';
    while (group.parent) {
        path = `${group.parent.name} / ${path}`;
        group = group.parent;
    }

    return path;
};
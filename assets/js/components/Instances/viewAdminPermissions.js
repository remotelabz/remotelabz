/**
 * Autorisation d'affichage de la console d'administration (route /instances/{uuid}/view/admin).
 *
 * Ce module reproduit, côté frontend, la règle appliquée par le serveur dans
 * InstanceController::viewInstanceAction() (src/Controller/InstanceController.php).
 * Le serveur reste l'autorité : ce contrôle sert uniquement à ne pas proposer
 * une icône qui redirigerait l'utilisateur vers la page d'accueil.
 *
 * Règle serveur pour type=admin :
 *   - administrateur de l'instance (ROLE_ADMINISTRATOR / ROLE_SUPER_ADMINISTRATOR), OU
 *   - auteur du lab, OU
 *   - owner ou admin d'un groupe rattaché au lab (Group::isElevatedUser)
 */

const SITE_ADMIN_ROLES = ['ROLE_ADMINISTRATOR', 'ROLE_SUPER_ADMINISTRATOR'];
const ELEVATED_GROUP_ROLES = ['admin', 'owner'];

const isSameId = (a, b) =>
    a !== undefined && a !== null && b !== undefined && b !== null && String(a) === String(b);

export const isSiteAdministrator = (user) =>
    !!user && Array.isArray(user.roles) && SITE_ADMIN_ROLES.some((role) => user.roles.includes(role));

/**
 * @param {Object} user - utilisateur courant (props.user)
 * @param {Object} lab - lab de l'instance (props.lab ou labInstance.lab)
 * @returns {boolean}
 */
export const canViewAdmin = (user, lab) => {
    if (!user || !Array.isArray(user.roles)) {
        return false;
    }

    // Les codes d'invitation n'ont jamais l'accès admin côté serveur
    if (user.code) {
        return false;
    }

    if (isSiteAdministrator(user)) {
        return true;
    }

    const author = lab?.author;
    if (author && (isSameId(author.id, user.id) || isSameId(author.uuid, user.uuid))) {
        return true;
    }

    const labGroups = Array.isArray(lab?.groups) ? lab.groups : [];
    const memberships = Array.isArray(user.groups) ? user.groups : [];
    if (labGroups.length === 0 || memberships.length === 0) {
        return false;
    }

    return memberships.some(
        (membership) =>
            ELEVATED_GROUP_ROLES.includes(membership.role) &&
            labGroups.some(
                (group) => isSameId(group.id, membership.id) || isSameId(group.uuid, membership.uuid)
            )
    );
};

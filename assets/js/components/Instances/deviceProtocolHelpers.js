/**
 * Utilitaires pour vérifier les protocoles de contrôle disponibles
 * Utilisable par tous les composants qui gèrent des instances de devices
 */

/**
 * Vérifie si le device supporte le protocole VNC
 * @param {Object} instance - L'instance du device
 * @returns {boolean}
 */
export const is_vnc = (instance) => {
  if (!instance?.controlProtocolTypeInstances?.length) {
    return false;
  }
  return instance.controlProtocolTypeInstances.some(
    element => element.controlProtocolType?.name === 'vnc'
  );
};

/**
 * Vérifie si le device supporte le protocole login
 * @param {Object} instance - L'instance du device
 * @returns {boolean}
 */
export const is_login = (instance) => {
  if (!instance?.controlProtocolTypeInstances?.length) {
    return false;
  }
  return instance.controlProtocolTypeInstances.some(
    element => element.controlProtocolType?.name === 'login'
  );
};

/**
 * Vérifie si le device supporte le protocole serial
 * @param {Object} instance - L'instance du device
 * @returns {boolean}
 */
export const is_serial = (instance) => {
  if (!instance?.controlProtocolTypeInstances?.length) {
    return false;
  }
  return instance.controlProtocolTypeInstances.some(
    element => element.controlProtocolType?.name === 'serial'
  );
};

/**
 * Vérifie si le device est physique
 * @param {Object} instance - L'instance du device
 * @returns {boolean}
 */
export const is_real = (instance) => {
  return instance?.device?.hypervisor?.name === 'physical' &&
         instance?.controlProtocolTypeInstances?.length > 0;
};

/**
 * Obtient tous les protocoles disponibles pour une instance
 * @param {Object} instance - L'instance du device
 * @returns {Object} Objet contenant les drapeaux pour chaque protocole
 */
export const getAvailableProtocols = (instance) => {
  return {
    vnc: is_vnc(instance),
    login: is_login(instance),
    serial: is_serial(instance),
    isPhysical: is_real(instance)
  };
};
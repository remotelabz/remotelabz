import Remotelabz from '../API';
import { toast } from 'react-toastify';

/**
 * Récupère les logs d'une instance de device
 * @param {string} instanceUuid - UUID de l'instance
 * @returns {Promise<Array>} Tableau des logs
 */
export const fetchDeviceLogs = async (instanceUuid) => {
  try {
    const response = await Remotelabz.instances.device.logs(instanceUuid);
    return response.data || [];
  } catch (error) {
    if (error.response?.status === 404) {
      return [];
    }
    
    const errorMessage = error?.response?.data?.message || 
      'An error happened while fetching instance logs. If this error persist, please contact an administrator.';
    
    toast.error(errorMessage);
    console.error(`Error fetching logs for ${instanceUuid}:`, error);
    throw error;
  }
};

/**
 * Configure un intervalle pour rafraîchir les logs périodiquement
 * @param {string} instanceUuid - UUID de l'instance
 * @param {Function} onLogsUpdate - Callback pour mettre à jour les logs
 * @param {number} interval - Intervalle de rafraîchissement en ms (défaut: 30000)
 * @returns {number} ID de l'intervalle pour le nettoyer plus tard
 */
export const startLogsPolling = (instanceUuid, onLogsUpdate, interval = 30000) => {
  const pollLogs = async () => {
    try {
      const logs = await fetchDeviceLogs(instanceUuid);
      onLogsUpdate(logs);
    } catch (error) {
      console.error('Error polling logs:', error);
    }
  };

  // Fetch immédiatement au démarrage
  pollLogs();

  // Puis rafraîchir périodiquement
  return setInterval(pollLogs, interval);
};

/**
 * Arrête le polling des logs
 * @param {number} intervalId - ID retourné par startLogsPolling
 */
export const stopLogsPolling = (intervalId) => {
  if (intervalId) {
    clearInterval(intervalId);
  }
};

/**
 * Formate un log pour l'affichage
 * @param {Object} log - L'objet log
 * @returns {string} Log formaté
 */
export const formatLogEntry = (log) => {
  return `[${log.createdAt}] ${log.content}`;
};

/**
 * Filtre les logs par date/contenu
 * @param {Array} logs - Tableau des logs
 * @param {string} searchTerm - Terme à rechercher
 * @returns {Array} Logs filtrés
 */
export const filterLogs = (logs, searchTerm) => {
  if (!searchTerm) return logs;
  
  const term = searchTerm.toLowerCase();
  return logs.filter(log => 
    log.content?.toLowerCase().includes(term) ||
    log.createdAt?.toLowerCase().includes(term)
  );
};

/**
 * Retourne les N derniers logs
 * @param {Array} logs - Tableau des logs
 * @param {number} count - Nombre de logs à retourner
 * @returns {Array} Derniers logs
 */
export const getLastLogs = (logs, count = 50) => {
  return logs.slice(-count);
};

/**
 * Exporte les logs en tant que texte
 * @param {Array} logs - Tableau des logs
 * @returns {string} Logs au format texte
 */
export const exportLogsAsText = (logs) => {
  return logs
    .map(log => formatLogEntry(log))
    .join('\n');
};
import React, { useState, useEffect } from 'react';
import { formatLogEntry, getLastLogs, filterLogs } from './deviceLogsHelpers';

/**
 * Composant pour afficher les logs d'un device
 * Réutilisable partout où on a besoin d'afficher des logs
 */
function DeviceLogs({ 
  logs = [], 
  showLogs = false, 
  maxLogs = 50,
  searchable = false,
  className = ''
}) {
  const [searchTerm, setSearchTerm] = useState('');

  if (!showLogs || !logs || logs.length === 0) {
    return null;
  }

  const displayedLogs = searchable 
    ? filterLogs(getLastLogs(logs, maxLogs), searchTerm)
    : getLastLogs(logs, maxLogs);

  return (
    <div className={`device-logs ${className}`}>
      {searchable && (
        <div className="logs-search mb-2">
          <input
            type="text"
            placeholder="Search logs..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="form-control form-control-sm"
          />
        </div>
      )}
      
      <pre className="d-flex flex-column mt-2">
        {displayedLogs.map((log) => (
          <code className="p-1" key={log.id}>
            {formatLogEntry(log)}
          </code>
        ))}
        {displayedLogs.length === 0 && (
          <code className="p-1 text-muted">No logs available</code>
        )}
      </pre>
    </div>
  );
}

export default DeviceLogs;
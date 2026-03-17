import { toast } from 'react-toastify';
import Remotelabz from '../API';
import React, { useState, useEffect, useCallback, useMemo } from 'react';
import OptimizedInstanceList from './OptimizedInstanceList';

function AllInstancesList(props = {labInstances: [], user:{}}) { 
    const [instances, setInstances] = useState([]);
    const [isLoading, setLoadingInstanceState] = useState(false);
    const [page, setPage] = useState(1);
    const [totalCount, setTotalCount] = useState(0);
    const [filter, setFilter] = useState(props.filter || 'all');
    const [subFilter, setSubFilter] = useState(props.subFilter || 'allInstances');
    const [searchUuid, setSearchUuid] = useState('');

    // États des actions bulk globales (par filtre, toutes instances confondues)
    const [isBulkStarting,  setIsBulkStarting]  = useState(false);
    const [isBulkStopping,  setIsBulkStopping]  = useState(false);
    const [isBulkResetting, setIsBulkResetting] = useState(false);
    const [isBulkLeaving,   setIsBulkLeaving]   = useState(false);

    const limit = 10;

    useEffect(() => {
        setLoadingInstanceState(true);
        const filterElement = document.getElementById("instance_filter");
        const subFilterElement = document.getElementById("instance_subFilter");
        const searchUuidElement = document.getElementById("instance_searchUuid");
        
        const currentFilter = filterElement?.value || 'none';
        const currentSubFilter = subFilterElement?.value || 'allInstances';
        const currentSearchUuid = searchUuidElement?.value || '';
        
        setFilter(currentFilter);
        setSubFilter(currentSubFilter);
        setSearchUuid(currentSearchUuid);

        refreshInstances();
        
        const interval = setInterval(refreshInstances, 60000);
        
        return () => {
            clearInterval(interval);
            setInstances([]);
            setLoadingInstanceState(false);
        }
    }, []);

    function refreshInstances() {
        const filterElement = document.getElementById("instance_filter");
        const subFilterElement = document.getElementById("instance_subFilter");
        const searchUuidElement = document.getElementById("instance_searchUuid");
        const pageElement = document.getElementById("instance_page");

        const currentFilter = filterElement?.value || filter || 'all';
        const currentSubFilter = subFilterElement?.value || subFilter || 'allInstances';
        const currentSearchUuid = searchUuidElement?.value || searchUuid || '';
        const currentPage = parseInt(pageElement?.value || page || 1);

        // Appel API avec UUID
        const request = Remotelabz.instances.lab.getAll(currentFilter, currentSubFilter, currentPage, currentSearchUuid);
    
        request.then(response => {
            //console.log('[AllInstancesList] Données reçues:', response.data);
            const formattedInstances = Array.isArray(response.data) ? response.data : [];
            setInstances(formattedInstances);
            setLoadingInstanceState(false);
            
            // Si c'est une recherche UUID et qu'on a des résultats
            if (currentSearchUuid && formattedInstances.length > 0) {
                toast.success(`Found ${formattedInstances.length} instance(s) for UUID`, {
                    autoClose: 3000,
                });
            } else if (currentSearchUuid && formattedInstances.length === 0) {
                toast.warning('No instance found for this UUID', {
                    autoClose: 5000,
                });
            }
        }).catch(error => {
            console.error('[AllInstancesList] Erreur lors du refresh:', error);
            if (error.response) {
                if (error.response.status <= 500) {
                    setInstances([]);
                    setLoadingInstanceState(false);
                    
                    if (error.response.status === 404 && currentSearchUuid) {
                        toast.error('No instance found for this UUID', {
                            autoClose: 5000,
                        });
                    }
                } else {
                    toast.error('An error occurred while retrieving instances. If this error persists, please contact an administrator.', {
                        autoClose: 10000,
                    });
                }
            }
        });
    }

    const handleStateUpdate = useCallback((action, uuid) => {
        console.log(`[AllInstancesList] Action ${action} sur ${uuid}`);
        
        if (action === 'start') {
            Remotelabz.instances.device.start(uuid)
                .then(() => {
                    toast.success('Starting device');
                    refreshInstances();
                })
                .catch((error) => {
                    const errorMsg = error?.response?.data?.message || 'Error when starting device';
                    toast.error(errorMsg);
                    console.error(error);
                });
        } else if (action === 'stop') {
            Remotelabz.instances.device.stop(uuid)
                .then(() => {
                    toast.success('Stopping device');
                    refreshInstances();
                })
                .catch((error) => {
                    const errorMsg = error?.response?.data?.message || 'Error when stopping device';
                    toast.error(errorMsg);
                    console.error(error);
                });
        } else if (action === 'reset') {
            Remotelabz.instances.device.reset(uuid)
                .then(() => {
                    toast.success('Resetting device');
                    refreshInstances();
                })
                .catch((error) => {
                    const errorMsg = error?.response?.data?.message || 'Error when instance reseting';
                    toast.error(errorMsg);
                    console.error(error);
                });
        }
    }, []);

    const handleLabDeleted = useCallback((deletedUuid) => {
        // Retirer immédiatement l'instance de la liste
        setInstances(prev => prev.filter(instance => instance.uuid !== deletedUuid));
        
        // Rafraîchir complètement la liste après un court délai
        setTimeout(() => {
            refreshInstances();
        }, 2000);
    }, []);

    const memoizedInstances = useMemo(() => instances, [instances]);

    /**
     * Lit les valeurs de filtre actives depuis les éléments cachés du DOM
     * (alimentés par index.html.twig via les hidden inputs ou les selects).
     */
    const getBulkFilterParams = useCallback(() => {
        const filterEl    = document.getElementById('instance_filter');
        const subFilterEl = document.getElementById('instance_subFilter');
        return {
            filter:    filterEl?.value    || filter    || 'none',
            subFilter: subFilterEl?.value || subFilter || 'allInstances',
        };
    }, [filter, subFilter]);

    /**
     * Appelle l'une des 4 routes bulk côté serveur avec le filtre courant.
     * L'action s'applique à TOUTES les instances correspondant au filtre,
     * pas seulement celles visibles à l'écran.
     *
     * @param {'start'|'stop'|'reset'|'leave'} action
     */
    const handleBulkFilterAction = useCallback(async (action) => {
        const { filter: f, subFilter: sf } = getBulkFilterParams();

        const setLoaders = {
            start: setIsBulkStarting,
            stop:  setIsBulkStopping,
            reset: setIsBulkResetting,
            leave: setIsBulkLeaving,
        };
        const setLoading = setLoaders[action];
        if (!setLoading) return;

        setLoading(true);

        const endpointMap = {
            start: '/api/instances/bulk/start-all',
            stop:  '/api/instances/bulk/stop-all',
            reset: '/api/instances/bulk/reset-all',
            leave: '/api/instances/bulk/leave-all',
        };

        const method = action === 'leave' ? 'DELETE' : 'POST';

        try {
            const response = await fetch(endpointMap[action], {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ filter: f, subFilter: sf }),
            });

            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.message || `HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                const count = data.started ?? data.stopped ?? data.reset ?? data.deletedCount ?? 0;
                const label = action === 'leave' ? 'deleted' : `${action}ed`;
                toast.success(
                    `Bulk ${action}: ${count} device/instance(s) ${label} across ${data.totalLabs} lab(s).`,
                    { autoClose: 6000 }
                );
            } else {
                toast.warning(
                    `Bulk ${action} completed with ${data.errors.length} error(s). Check the console for details.`,
                    { autoClose: 8000 }
                );
                data.errors.forEach(e => {
                    toast.error(`${e.name || e.uuid}: ${e.error}`, { autoClose: 6000 });
                });
            }

            // Rafraîchir la liste après l'opération
            setTimeout(() => refreshInstances(), 2000);

        } catch (error) {
            console.error(`[AllInstancesList] Bulk ${action} error:`, error);
            toast.error(
                error.message || `An error occurred during bulk ${action}. Please try again.`,
                { autoClose: 10000 }
            );
        } finally {
            setLoading(false);
        }
    }, [getBulkFilterParams, refreshInstances]);

    const isBulkBusy = isBulkStarting || isBulkStopping || isBulkResetting || isBulkLeaving;

    // Exposer handleBulkFilterAction au DOM pour que les boutons du Twig puissent l'appeler
    useEffect(() => {
        const handler = (e) => {
            const action = e.detail?.action;
            if (action) handleBulkFilterAction(action);
        };
        window.addEventListener('remotelabz:bulk-action', handler);
        return () => window.removeEventListener('remotelabz:bulk-action', handler);
    }, [handleBulkFilterAction]);

    // Mettre à jour les boutons du Twig en fonction de l'état busy
    useEffect(() => {
        const btnIds = ['bulk-start-btn', 'bulk-stop-btn', 'bulk-reset-btn', 'bulk-leave-btn'];
        btnIds.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.disabled = isBulkBusy;
        });
    }, [isBulkBusy]);

    return (
        <>
            <div style={{ 
                height: 'calc(100vh - 300px)', 
                minHeight: '400px',
                display: 'flex',
                flexDirection: 'column'
            }}>

            {memoizedInstances.length > 0 ? (
                <OptimizedInstanceList
                    instances={memoizedInstances}
                    user={props.user}
                    onStateUpdate={handleStateUpdate}
                    onLabDeleted={handleLabDeleted}
                />
            ) : (
                !isLoading && (
                    <div style={{
                        padding: '24px',
                        textAlign: 'center',
                        color: '#666',
                        borderRadius: '4px',
                        margin: '16px'
                    }}>
                        <p>No available instance</p>
                    </div>
                )
            )}

            {isLoading && (
                <div style={{
                    padding: '24px',
                    textAlign: 'center',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    gap: '16px'
                }}>
                    <div className="dot-bricks"></div>
                    <span>Loading instances...</span>
                </div>
            )}
            </div>
        </>
    );
}

export default AllInstancesList;
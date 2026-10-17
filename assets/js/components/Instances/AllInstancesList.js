import { toast } from 'react-toastify';
import Remotelabz from '../API';
import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { Pagination } from 'react-bootstrap';
import OptimizedInstanceList from './OptimizedInstanceList';

// Valeurs autorisées pour le nombre d'instances chargées par page.
const PAGE_SIZE_CHOICES = [10, 20, 50, 100];

function AllInstancesList(props = {labInstances: [], user:{}}) { 
    const [instances, setInstances] = useState([]);
    const [isLoading, setLoadingInstanceState] = useState(false);
    const [page, setPage] = useState(1);
    const [limit, setLimit] = useState(() => {
        const fromDom = parseInt(document.getElementById('instance_limit')?.value, 10);
        if (PAGE_SIZE_CHOICES.includes(fromDom)) return fromDom;
        const fromProps = parseInt(props.limit, 10);
        return PAGE_SIZE_CHOICES.includes(fromProps) ? fromProps : 10;
    });
    const [totalCount, setTotalCount] = useState(0);
    const [filter, setFilter] = useState(props.filter || 'all');
    const [subFilter, setSubFilter] = useState(props.subFilter || 'allInstances');
    const [searchUuid, setSearchUuid] = useState('');

    // États des actions bulk globales (par filtre, toutes instances confondues)
    const [isBulkStarting,  setIsBulkStarting]  = useState(false);
    const [isBulkStopping,  setIsBulkStopping]  = useState(false);
    const [isBulkResetting, setIsBulkResetting] = useState(false);
    const [isBulkLeaving,   setIsBulkLeaving]   = useState(false);

    // Références pour que le rafraîchissement périodique utilise toujours la
    // page et la taille de page courantes, sans recréer l'intervalle.
    const pageRef = useRef(page);
    const limitRef = useRef(limit);
    useEffect(() => { pageRef.current = page; }, [page]);
    useEffect(() => { limitRef.current = limit; }, [limit]);

    // Rafraîchissement stable : lit toujours les filtres depuis le DOM et la
    // page/limit depuis les refs, afin de ne pas recréer l'intervalle ni les
    // callbacks à chaque changement.
    const refreshInstances = useCallback(() => {
        setLoadingInstanceState(true);

        const filterElement = document.getElementById("instance_filter");
        const subFilterElement = document.getElementById("instance_subFilter");
        const searchUuidElement = document.getElementById("instance_searchUuid");

        const currentFilter = filterElement?.value || 'none';
        const currentSubFilter = subFilterElement?.value || 'allInstances';
        const currentSearchUuid = searchUuidElement?.value || '';
        const currentPage = pageRef.current;
        const currentLimit = limitRef.current;

        // Appel API paginé. Le total est renvoyé via l'en-tête X-Total-Count.
        const request = Remotelabz.instances.lab.getAll(currentFilter, currentSubFilter, currentPage, currentSearchUuid, currentLimit);
    
        request.then(response => {
            const formattedInstances = Array.isArray(response.data) ? response.data : [];
            setInstances(formattedInstances);
            setLoadingInstanceState(false);

            // Récupérer le nombre total d'instances (toutes pages confondues)
            const totalHeader = response.headers?.['x-total-count'];
            const total = parseInt(totalHeader, 10);
            if (!Number.isNaN(total)) {
                setTotalCount(total);

                // Si la page courante n'existe plus (ex : instances supprimées),
                // revenir à la dernière page valide.
                const maxPage = Math.max(1, Math.ceil(total / currentLimit));
                if (currentPage > maxPage) {
                    setPage(maxPage);
                }
            }
            
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
    }, []);

    // Au montage : synchronise les filtres depuis le DOM et démarre le
    // rafraîchissement périodique.
    useEffect(() => {
        setFilter(document.getElementById("instance_filter")?.value || 'none');
        setSubFilter(document.getElementById("instance_subFilter")?.value || 'allInstances');
        setSearchUuid(document.getElementById("instance_searchUuid")?.value || '');

        const interval = setInterval(refreshInstances, 60000);

        return () => {
            clearInterval(interval);
            setInstances([]);
            setLoadingInstanceState(false);
        }
    }, [refreshInstances]);

    // Recharge la liste au montage puis à chaque changement de page ou de
    // taille de page.
    useEffect(() => {
        refreshInstances();
    }, [page, limit, refreshInstances]);

    // Persister la taille de page dans le formulaire de filtres afin qu'elle
    // soit conservée lors d'une nouvelle soumission (changement de filtre).
    useEffect(() => {
        const limitInput = document.getElementById('instance_limit');
        if (limitInput) {
            limitInput.value = limit;
        }
    }, [limit]);

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

    // Nombre total de pages (au moins 1) et fenêtre de pages à afficher.
    const totalPages = Math.max(1, Math.ceil(totalCount / limit));

    /**
     * Construit la liste des numéros de page à afficher, avec des ellipses
     * autour de la page courante pour rester compact même avec beaucoup de pages.
     */
    const pageItems = useMemo(() => {
        const items = [];
        const windowSize = 2; // pages affichées de part et d'autre de la page courante
        const last = totalPages;

        const pushPage = (p) => items.push(p);

        if (last <= 7) {
            for (let p = 1; p <= last; p++) pushPage(p);
            return items;
        }

        pushPage(1);
        const start = Math.max(2, page - windowSize);
        const end = Math.min(last - 1, page + windowSize);

        if (start > 2) items.push('start-ellipsis');
        for (let p = start; p <= end; p++) pushPage(p);
        if (end < last - 1) items.push('end-ellipsis');
        pushPage(last);

        return items;
    }, [page, totalPages]);

    const handlePageChange = useCallback((newPage) => {
        if (newPage < 1 || newPage > totalPages || newPage === page) return;
        setPage(newPage);
    }, [page, totalPages]);

    const handleLimitChange = useCallback((event) => {
        const newLimit = parseInt(event.target.value, 10);
        if (!PAGE_SIZE_CHOICES.includes(newLimit)) return;
        setLimit(newLimit);
        // Revenir en première page : la pagination courante n'a plus de sens
        // avec une taille de page différente.
        setPage(1);
    }, []);

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

            {totalCount > 0 && (
                <div className="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3 px-2">
                    <div className="d-flex align-items-center gap-2">
                        <label htmlFor="instance_limit_select" className="mb-0 text-nowrap">
                            Instances per page:
                        </label>
                        <select
                            id="instance_limit_select"
                            className="form-select form-select-sm"
                            style={{ width: 'auto', minWidth: '4rem' }}
                            value={limit}
                            onChange={handleLimitChange}
                        >
                            {PAGE_SIZE_CHOICES.map(size => (
                                <option key={size} value={size}>{size}</option>
                            ))}
                        </select>
                        <span className="text-muted small text-nowrap">
                            {((page - 1) * limit) + 1}-{Math.min(page * limit, totalCount)} of {totalCount}
                        </span>
                    </div>

                    {totalPages > 1 && (
                        <Pagination className="mb-0">
                            <Pagination.Prev
                                disabled={page <= 1}
                                onClick={() => handlePageChange(page - 1)}
                            />

                            {pageItems.map((item) => {
                                if (item === 'start-ellipsis' || item === 'end-ellipsis') {
                                    return <Pagination.Ellipsis key={item} disabled />;
                                }
                                return (
                                    <Pagination.Item
                                        key={item}
                                        active={item === page}
                                        onClick={() => handlePageChange(item)}
                                    >
                                        {item}
                                    </Pagination.Item>
                                );
                            })}

                            <Pagination.Next
                                disabled={page >= totalPages}
                                onClick={() => handlePageChange(page + 1)}
                            />
                        </Pagination>
                    )}
                </div>
            )}
        </>
    );
}

export default AllInstancesList;
import { ToastContainer, toast } from 'react-toastify';
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
            console.log('[AllInstancesList] Données reçues:', response.data);
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

    return (
        <>
            <ToastContainer
                position="top-right"
                autoClose={5000}
                hideProgressBar={false}
                closeOnClick
                pauseOnHover
                draggable
                pauseOnFocusLoss={false}
            />
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
                        backgroundColor: '#f5f5f5',
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
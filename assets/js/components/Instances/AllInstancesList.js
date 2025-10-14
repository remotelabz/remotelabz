import { ToastContainer, toast } from 'react-toastify';
import Remotelabz from '../API';
import React, { useState, useEffect, useCallback, useMemo } from 'react';
import OptimizedInstanceList from './OptimizedInstanceList';
import { Button } from 'react-bootstrap';

function AllInstancesList(props = {labInstances: [], user:{}}) { 
    const [instances, setInstances] = useState([]);
    const [isLoading, setLoadingInstanceState] = useState(false);
    const [page, setPage] = useState(1);
    const [totalCount, setTotalCount] = useState(0);
    const [filter, setFilter] = useState(props.filter || 'all');
    const [subFilter, setSubFilter] = useState(props.subFilter || 'allInstances');
    
    const limit = 10;

    useEffect(() => {
        setLoadingInstanceState(true);
        refreshInstances();
        
        const interval = setInterval(refreshInstances, 60000);
        
        return () => {
            clearInterval(interval);
            setInstances([]);
            setLoadingInstanceState(false);
        }
    }, [filter, subFilter, page]);

    function refreshInstances() {
        const filterElement = document.getElementById("instance_filter");
        const subFilterElement = document.getElementById("instance_subFilter");
        const pageElement = document.getElementById("instance_page");

        const currentFilter = filterElement?.value || filter || 'all';
        const currentSubFilter = subFilterElement?.value || subFilter || 'allInstances';
        const currentPage = parseInt(pageElement?.value || page || 1);

        const request = Remotelabz.instances.lab.getAll(currentFilter, currentSubFilter, currentPage);
    
        request.then(response => {
            console.log('[AllInstancesList] Données reçues:', response.data);
            // S'assurer que les données sont au bon format
            const formattedInstances = Array.isArray(response.data) ? response.data : [];
            setInstances(formattedInstances);
            setLoadingInstanceState(false);
        }).catch(error => {
            console.error('[AllInstancesList] Erreur lors du refresh:', error);
            if (error.response) {
                if (error.response.status <= 500) {
                    setInstances([]);
                    setLoadingInstanceState(false);
                } else {
                    toast.error('Une erreur est survenue lors de la récupération des instances. Si cette erreur persiste, veuillez contacter un administrateur.', {
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
                    toast.success('Démarrage de l\'instance demandé.');
                    refreshInstances();
                })
                .catch((error) => {
                    const errorMsg = error?.response?.data?.message || 'Erreur lors du démarrage de l\'instance.';
                    toast.error(errorMsg);
                    console.error(error);
                });
        } else if (action === 'stop') {
            Remotelabz.instances.device.stop(uuid)
                .then(() => {
                    toast.success('Arrêt de l\'instance demandé.');
                    refreshInstances();
                })
                .catch((error) => {
                    const errorMsg = error?.response?.data?.message || 'Erreur lors de l\'arrêt de l\'instance.';
                    toast.error(errorMsg);
                    console.error(error);
                });
        } else if (action === 'reset') {
            Remotelabz.instances.device.reset(uuid)
                .then(() => {
                    toast.success('Réinitialisation de l\'instance demandée.');
                    refreshInstances();
                })
                .catch((error) => {
                    const errorMsg = error?.response?.data?.message || 'Erreur lors de la réinitialisation de l\'instance.';
                    toast.error(errorMsg);
                    console.error(error);
                });
        }
    }, []);

    const memoizedInstances = useMemo(() => instances, [instances]);
    console.log("[AllInstancesList]:memoizedInstances avant le return",memoizedInstances);
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

            {memoizedInstances.length > 0 ? (
                <OptimizedInstanceList
                    instances={memoizedInstances}
                    user={props.user}
                    onStateUpdate={handleStateUpdate}
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
                        <p>Aucune instance disponible</p>
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
                    <span>Chargement des instances...</span>
                </div>
            )}
        </>
    );
}

export default AllInstancesList;
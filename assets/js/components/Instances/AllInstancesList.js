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
    const [filter, setFilter] = useState('all');
    const [subFilter, setSubFilter] = useState('allInstances');

    const limit = 10; // Instances par page (côté serveur)

    useEffect(() => {
        setLoadingInstanceState(true);
        refreshInstances();
        
        // Polling toutes les 60 secondes (au lieu de 30)
        const interval = setInterval(refreshInstances, 60000);
        
        return () => {
            clearInterval(interval);
            setInstances([]);
            setLoadingInstanceState(false);
        }
    }, [filter, subFilter, page]);

    function refreshInstances() {
        let request;
        
        // Récupérer les filtres depuis le DOM s'ils existent
        const filterElement = document.getElementById("instance_filter");
        const subFilterElement = document.getElementById("instance_subFilter");
        const pageElement = document.getElementById("instance_page");

        const currentFilter = filterElement?.value || filter || 'all';
        const currentSubFilter = subFilterElement?.value || subFilter || 'allInstances';
        const currentPage = parseInt(pageElement?.value || page || 1);

        // API call
        request = Remotelabz.instances.lab.getAll(currentFilter, currentSubFilter, currentPage);
    
        request.then(response => {
            setInstances(response.data || []);
            setLoadingInstanceState(false);
        }).catch(error => {
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

    // Fonction de rafraîchissement pour OptimizedInstanceList
    const handleStateUpdate = useCallback((action, uuid) => {
        // Appeler l'API appropriée selon l'action
        if (action === 'start') {
            Remotelabz.instances.device.start(uuid)
                .then(() => {
                    toast.success('Démarrage de l\'instance demandé.');
                    refreshInstances();
                })
                .catch((error) => {
                    toast.error('Erreur lors du démarrage de l\'instance. Veuillez réessayer plus tard.');
                    console.error(error);
                });
        } else if (action === 'stop') {
            Remotelabz.instances.device.stop(uuid)
                .then(() => {
                    toast.success('Arrêt de l\'instance demandé.');
                    refreshInstances();
                })
                .catch((error) => {
                    toast.error('Erreur lors de l\'arrêt de l\'instance. Veuillez réessayer plus tard.');
                    console.error(error);
                });
        }
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

            {/* Filtres (optionnel - garder l'ancien code si nécessaire) */}
            {/* Les filtres peuvent rester ici pour la compatibilité */}

            {/* Liste virtualisée optimisée */}
            {memoizedInstances.length > 0 ? (
                <OptimizedInstanceList
                    instances={Array.isArray(memoizedInstances) ? memoizedInstances : []}
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
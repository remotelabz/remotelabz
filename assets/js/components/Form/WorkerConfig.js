import Noty from 'noty';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import React, { useState, useEffect } from 'react';

function WorkerConfig(props = {workers, nbWorkers}) { 
    const [form, setForm] = useState();
    const [nbWorkers, setNbWorkers] = useState(props.nbWorkers);
    const [workers, setWorkers] = useState(props.workers);
    const [refreshed, setRefreshed] = useState(0);

    useEffect(()=> {
    printForm(nbWorkers)
    }, [nbWorkers, refreshed])




    function printForm(nbWorkers) {
        var list = [];
        for( let i = 1; i<=nbWorkers; i++) {
            list.push(<>
            <div className="row" >
                <label for={`worker${i}`} className='form-label'>Worker {i}</label>
            </div>
            <div className='row g-2'>
                <div className='col-10'>
                    <input type="text" id={workers[i-1] != undefined ? (workers[i-1].id) : ""} className="form-control mb-2" name="workers" defaultValue={workers[i-1] != undefined ? workers[i-1].IPv4 :""} readOnly={workers[i-1] != undefined ? (!workers[i-1].available) : false}/>
                </div>
                <div className="col-2">
                    {workers[i-1] == undefined ?
                        <button type="button" className='btn btn-danger' onClick={removeField}><SVG name="clear" /></button>
                        :
                        <>{workers[i-1].available == true ? <button type="button" className='btn btn-warning mr-2' onClick={() => changeAvailable(workers[i-1].id, 0)} >Disable</button> : <button type="button" className='btn btn-warning mr-2' onClick={() => changeAvailable(workers[i-1].id, 1)} >Enable</button>}
                        <button type="button" className='btn btn-danger' onClick={() => deleteWorker(workers[i-1].id)}>Delete</button></>
                    }
                    
                </div>
            </div></>)
        }
        list.push(<><button type="button" className='btn btn-info mt-2' onClick={addField}><SVG name="plus" /></button></>)
        list.push(<><input type="submit" className='btn btn-success mt-2 ml-3' value="Submit"/></>)
        setForm(list);
    }

    function addField() {
        setNbWorkers(nbWorkers +1);
    }

    function removeField() {
        setNbWorkers(nbWorkers -1);
    }
    function deleteWorker(id) {
        Remotelabz.configWorker.delete(id).then(()=> {
            refresh();
        });
    }
    function changeAvailable(id, available) {
        Remotelabz.configWorker.update(id, {"available": available}).then(()=> {
            refresh();
        });
    }
    
    function refresh() {
        Remotelabz.configWorker.all().then((result)=> {
            setWorkers(result.data);
            setNbWorkers(result.data.length);
            setRefreshed(!refreshed);
        });
    }

    function handleSubmit(e) {
        e.preventDefault();
        let workerElements = [];
        for (let el of e.target.elements) {
            if (el.name == "workers") {
                workerElements.push(el);
            }
        }
        for(let workerElement of workerElements) {
            if (workerElement.id !== "") {
                Remotelabz.configWorker.update(workerElement.id, {"IPv4": workerElement.value});
            }
            else {
                let exists = false;
                Remotelabz.configWorker.all().then((result)=> {
                    setWorkers(result.data)
                });
                console.group(workers);
                 for (let i =0; i < workers.length; i++) {
                    if (workerElement.value == workers[i].IPv4) {
                        exists = true;
                    }
                }
                if (exists == false) {
                    Remotelabz.configWorker.new({"IPv4": workerElement.value});
                }
                else {
                    new Noty({ type: 'error', text: 'worker IP ' + workerElement.value +' already exists.' }).show();
                    
                }
            }

        }
        refresh();
 
    }

    return (<form onSubmit={handleSubmit}>
    {form}
    </form>)
}

export default WorkerConfig;
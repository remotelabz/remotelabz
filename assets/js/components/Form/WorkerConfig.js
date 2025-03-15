import Noty from 'noty';
import Remotelabz from '../API';
import SVG from '../Display/SVG';
import React, { useState, useEffect } from 'react';

function WorkerConfig(props = {workers, nbWorkers}) { 
    const [form, setForm] = useState();
    const [nbWorkers, setNbWorkers] = useState(props.nbWorkers);
    const [workers, setWorkers] = useState(props.workers);
    const [nbNewWorker, setNbNewWorker] = useState(0);
    const [newFields, setNewFields] = useState([]);
    const [newWorkers, setNewWorkers] = useState();

    useEffect(()=> {
    refresh()
    }, [])

    useEffect(()=> {
        printNewWorkers()
    }, [newFields]);

    function addField() {
        let fields = newFields;
        if (fields.length != 0) {
            fields.sort();
            var i = fields[fields.length -1] +1;
        }
        else {
            var i =1;
        }
        
        setNewFields([...fields, i]);        
        setNbNewWorker(i);
    }

    function removeField(i) {
        const index = newFields.indexOf(i);
        if (index > -1) { 
           let fields =  [...newFields];
           fields.splice(index, 1); 
           setNewFields(fields);
           setNbNewWorker(nbNewWorker - 1);
        }
    }

    function printNewWorkers() {
        let elements = newFields.map((i)=> {
            return (<div id={`workerField${i}`} key={`workerField${i}`}>
                <div className='row' >
                    <label className='form-label'>New worker {i}</label>
                </div>
                <div className='row g-2'>
                    <div className='col-10'>
                        <input type='text' className='form-control mb-2' name='workers' defaultValue='' readOnly={false}/>
                    </div>
                    <div className='col-2'>
                        <button type='button' className='btn btn-danger' onClick={() => removeField(i)}><SVG name='clear' /></button>
                    </div>
                </div>
            </div>)
        });

        setNewWorkers(elements);
    }
    function deleteWorker(id) {
        Remotelabz.configWorker.delete(id).then(()=> {
            refresh();
        })
        .catch((error)=> {
            if (error.response.data.message.includes("is used by an instance")) {
                new Noty({
                    text: error.response.data.message,
                    type: 'error'
                }).show()
            }
        });
    }
    function changeAvailable(id, available) {
        Remotelabz.configWorker.update(id, {"available": available}).then(()=> {
            let msg;
            if (available == 1 )
                msg='Worker is enabled';
            else 
                msg='Worker is disabled';

            new Noty({
                text: msg,
                type: 'success',
                timeout: 2000
            }).show();

            refresh();
        });
    }
    
    function refresh() {
        Remotelabz.configWorker.all().then((result)=> {
            //console.log(result);
            setWorkers(result.data);
            setNbWorkers(result.data.length);
            let dbWorkers = result.data.sort((a,b)=>{return a.queueName.replace(/messages_worker/,"") - b.queueName.replace(/messages_worker/,"")});

            var list = dbWorkers.map((worker) => {
                return (
                    <div key={worker.id}>
                        <div className="row" >
                            <label for={`worker${worker.queueName.replace(/messages_worker/,"")}`} className='form-label'>Worker {worker.queueName.replace(/messages_worker/,"")}</label>
                        </div>
                        <div className='row g-2'>
                            <div className='col-10'>
                                <input type="text" id={worker.id} className="form-control mb-2" name="workers" defaultValue={worker.IPv4} readOnly={!worker.available}/>
                            </div>
                            <div className="col-2">
                                    {worker.available == true ? <button type="button" className='btn btn-warning mr-2' onClick={() => changeAvailable(worker.id, 0)} >Disable</button> : <button type="button" className='btn btn-success mr-2' onClick={() => changeAvailable(worker.id, 1)} >Enable</button>}
                                    <button type="button" className='btn btn-danger' onClick={() => deleteWorker(worker.id)}>Delete</button>
                                
                            </div>
                        </div>
                    </div>
                )
            })

            setForm(list);
            setNewWorkers([]);
        });
    }

    function handleSubmit(e) {
        e.preventDefault();
        let workerElements = [];
        let promises = [];
        for (let el of e.target.elements) {
            if (el.name == "workers") {
                workerElements.push(el);
            }
        }
        for(let workerElement of workerElements) {
            if (workerElement.id !== "") {
                promises.push(()=>Remotelabz.configWorker.update(workerElement.id, {"IPv4": workerElement.value}));
            }
            else {
                let exists = false;
                let workersToAdd = [...workers];
                 for (let i =0; i < workersToAdd.length; i++) {
                    if (workerElement.value == workersToAdd[i].IPv4) {
                        exists = true;
                    }
                }
                if (exists == false) {
                    promises.push(()=>Remotelabz.configWorker.new({"IPv4": workerElement.value}));
                    workersToAdd.push({"IPv4": workerElement.value});
                }
                else {
                    new Noty({ type: 'error', text: 'worker IP ' + workerElement.value +' already exists.' }).show();
                    
                }
            }
        }
        const requests = () => {
            promises.push(()=>{
                setNewFields([])
                refresh()
            });
            return promises.reduce((prev, promise) => {
                return prev
                  .then(promise)
                  .catch(err => {
                    console.warn('err', err.message);
                  });
              }, Promise.resolve());
        }
        requests();
    }

    return (<form onSubmit={handleSubmit}>
        {form}
        <div id="newWorkers">{newWorkers}</div>
        <button type="button" className='btn btn-info mt-2' onClick={addField}><SVG name="plus" /></button>
        <input type="submit" className='btn btn-success mt-2 ml-3' value="Submit"/>
    </form>)
}

export default WorkerConfig;
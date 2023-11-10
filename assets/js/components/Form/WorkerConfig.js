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
        console.log(newFields);
        printNewWorkers()
    }, [newFields]);

    function addField() {
        let fields = newFields;
        console.log(fields.length);
        if (fields.length != 0) {
            fields.sort();
            console.log(fields);
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
            console.log(index);
           let fields =  [...newFields];
           fields.splice(index, 1); 
           console.log(fields);
           console.log(newFields);
           setNewFields(fields);
           setNbNewWorker(nbNewWorker - 1);
        }
    }

    function printNewWorkers() {
        console.log(newFields);
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
            console.log(result.data);
            let dbWorkers = result.data;
            let nbDbWorkers = result.data.length;

            var list = dbWorkers.map((worker, id) => {
                return (
                    <div key={worker.id}>
                        <div className="row" >
                            <label for={`worker${id+1}`} className='form-label'>Worker {id+1}</label>
                        </div>
                        <div className='row g-2'>
                            <div className='col-10'>
                                <input type="text" id={worker.id} className="form-control mb-2" name="workers" defaultValue={worker.IPv4} readOnly={worker.available}/>
                            </div>
                            <div className="col-2">
                                    {worker.available == true ? <button type="button" className='btn btn-warning mr-2' onClick={() => changeAvailable(worker.id, 0)} >Disable</button> : <button type="button" className='btn btn-warning mr-2' onClick={() => changeAvailable(worker.id, 1)} >Enable</button>}
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
        setTimeout(refresh(), 1000);
 
    }

    return (<form onSubmit={handleSubmit}>
        {form}
        <div id="newWorkers">{newWorkers}</div>
        <button type="button" className='btn btn-info mt-2' onClick={addField}><SVG name="plus" /></button>
        <input type="submit" className='btn btn-success mt-2 ml-3' value="Submit"/>
    </form>)
}

export default WorkerConfig;
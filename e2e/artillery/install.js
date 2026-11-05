import { testInstall } from '../commands/install.js';


async function artilleryScript(page){
    await testInstall(page);
}

export { artilleryScript }
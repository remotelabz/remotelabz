function labController($scope, $http, $location, $uibModal, $rootScope, $q, $log, $compile, $timeout) {
	
	//$scope.testAUTH("/lab"); //TEST AUTH
	$('body').removeClass().addClass('skin-blue sidebar-mini sidebar-collapse');
	$('html ').css({'min-height' : '100% !important', 'height': '100%'});
	$('body').css({'min-height' : '100% !important', 'height': '100%'});
	$('.content-wrapper').css({'min-height' : '100% !important', 'height': '100%'});
	$('mainDIV').css({'min-height' : '100% !important', 'height': '100%'});
	
	contextMenuInitConn()
	contextMenuInit()
	
	
	
	$scope.node={};
	$scope.allNodes={};
	$scope.allNetworks={};
	$scope.node={};
	$scope.interfList={};
	$scope.interfListCount=false;
	$scope.ready=false;
	$scope.tempConn= new Object();
	$scope.changedCursorNode=false;
	$scope.changedCursorNet=false;
	$scope.addNewObject={};
	$scope.fullPathToFile=$rootScope.lab;
	$scope.mainDivHeight = parseInt($('html').height()) - 15;
	$scope.collapseSidebar = function(){
		$('body').removeClass('sidebar-expanded-on-hover').addClass('sidebar-collapse');
	}
	
	$scope.mainFieldClick = function($event,src){
		$('body').removeClass('sidebar-expanded-on-hover').addClass('sidebar-collapse');
		//console.log($event)
		if (src == undefined) {$scope.addNewObject={}; $scope.addNewObject=$event}
		else {$scope.addNewObject={}; $scope.addNewObject.pageX=$('#'+src).offset().left; $scope.addNewObject.pageY=$('#'+src).offset().top;}
		
		if ($scope.changedCursorNode){
			$scope.changedCursorNode=false
			$scope.openModal('addNode');
		}
		if ($scope.changedCursorNet){
			$scope.changedCursorNet=false
			$scope.openModal('addNet');
		}
	}
	
	$scope.closeLab = function(){
		jsPlumb.detachEveryConnection();
		jsPlumb.reset();
		$http({
				method: 'DELETE',
				url: '/api/labs/close'})
				.then(
					function successCallback(response) {
						console.log(response)
						$location.path('/main')
					}, 
					function errorCallback(response) {
						console.log(response)
						$location.path('/main')
					}
		);
	}
	$scope.compileNewElement = function(el, id){
		$('#canvas').append($compile(el)($scope))
		jsPlumbNodeInit($('#nodeID_'+id))
	}
	$scope.startstopNode = function(id){
		if(!$scope.node[id].upstatus){
			//START NODE //START
			$scope.node[id].loadclassShow=true;
			$http.get('/api/labs'+$rootScope.lab+'/nodes/'+id+'/start').then(
				function successCallback(response){
					$scope.node[id].upstatus=true;
					$scope.node[id].loadclassShow=false;
				}, function errorCallback(response){
					$scope.node[id].upstatus=false;
					$scope.node[id].loadclassShow=false;
					console.log('Server Error');
					console.log(response.data);
					toastr["error"](response.data.message, "Error");
				}
			);
			//$timeout(function () {
			//	$scope.node[id].upstatus=true;
			//	$scope.node[id].loadclassShow=false;
			//}, 4000);
			
			//START NODE //END
		} else {
			//STOP NODE //START
			$scope.node[id].loadclassShow=true;
			$http.get('/api/labs'+$rootScope.lab+'/nodes/'+id+'/stop').then(
				function successCallback(response){
					$scope.node[id].upstatus=false;
					$scope.node[id].loadclassShow=false;
				}, function errorCallback(response){
					$scope.node[id].upstatus=false;
					$scope.node[id].loadclassShow=false;
					console.log('Server Error');
					console.log(response.data);
					toastr["error"](response.data.message, "Error");
				}
			);
			//$timeout(function () {
			//	$scope.node[id].upstatus=false;
			//	$scope.node[id].loadclassShow=false;
			//}, 4000);
			//STOP NODE //END
		}
	}
	$scope.newConnModal = function(conn){
		if ($scope.ready){
			//console.log(conn)
			$scope.addConnSrc=conn.source
			$scope.addConnDst=conn.target
			jsPlumb.detach(conn)
			$scope.openModal('addConn');
			$scope.ready=false;
		}
	}
	$scope.addNewNodeCursor = function(type){
		$scope.changedCursorNode=false;
		$scope.changedCursorNet=false;
		$('body').removeClass('sidebar-expanded-on-hover').addClass('sidebar-collapse');
		switch (type) {
			case 'node':
				$scope.changedCursorNode=true;
				break;
			case 'network':
				$scope.changedCursorNet=true;
				break;
			}
	}
	
	$scope.tempElID='';
	$scope.deleteEl = function(){
		$scope.tempElID=$('#tempElID').val()
		var type = ($scope.tempElID.search('node') != -1) ? 'node' : 'network';
		var id = (type == 'node') ? $scope.tempElID.replace('nodeID_','') : $scope.tempElID.replace('networkID_','');
		if (confirm('Are you sure?')){
				console.log(id+' '+type)
				$http({
					method: 'DELETE',
					url:'/api/labs'+$rootScope.lab+'/'+type+'s/'+id}).then(
					function successCallback(response){
						console.log(response)
						$('#'+type+'ID_'+id).remove()
					}, function errorCallback(response){
						console.log('Server Error');
						console.log(response);
					}
				);
		}
	}
	
	$scope.delConn = function(conn){
		var ifs = conn.getParameters()
		var src = {}
		var dst = {}
		src.type = (conn.source.id.search('node') != -1) ? 'node' : 'network';
		dst.type = (conn.target.id.search('node') != -1) ? 'node' : 'network';
		src.id = (src.type == 'node') ? conn.source.id.replace('nodeID_','') : conn.source.id.replace('networkID_','');
		dst.id = (dst.type == 'node') ? conn.target.id.replace('nodeID_','') : conn.target.id.replace('networkID_','');
		var urlCalls = [];
		if (src.type == 'node'){
			$scope.getIntrfInfo(src.id).then(function(something){
				//console.log(something)
				//console.log(ifs)
				var prepare = something.ethernet
				var finalPrepare = {}
				jQuery.extend(prepare, something.serial)
				var tempObj = {}
				for (var key in something.ethernet){
					if (something.ethernet[key].name==ifs.interfSrc) {something.ethernet[key].network_id="";
						tempObj[''+key+'']=String(something.ethernet[key].network_id)
						jQuery.extend(finalPrepare, tempObj)
					}
				}
				for (var key in something.serial){
					var tempsrl=something.serial[key].remote_id+':'+something.serial[key].remote_if
					tempObj[key]=(tempsrl == "0:0")? '' : String(tempsrl);
					jQuery.extend(finalPrepare, tempObj)
				}
				
				console.log(finalPrepare)
				$http({
					method: 'PUT',
					url:'/api/labs'+$rootScope.lab+'/nodes/'+src.id+'/interfaces',
					data: finalPrepare}).then(
					function successCallback(response){
						console.log(response)
						jsPlumb.detach(conn);
						$scope.anychanges=false;
						//$scope.interfList = response.data.data;
					}, function errorCallback(response){
						console.log('Server Error');
						console.log(response);
					}
				);
				//console.log($scope.node[src.id].ifList)
			})
		}
		if (dst.type == 'node'){
			$scope.getIntrfInfo(dst.id).then(function(something){
				//console.log(something)
				var prepare = something.ethernet
				for (var key in prepare){
					if (prepare[key].name==ifs.interfTrg) {prepare[key].network_id=0;break;}
				}
				console.log(prepare)
			})
		}
	}
	
	$scope.getIntrfInfo = function(id){
		var deferred = $q.defer();
		$scope.interfList={};
		$scope.interfListCount=true;
		$http.get('/api/labs'+$rootScope.lab+'/nodes/'+id+'/interfaces').then(
			function successCallback(response){
				//console.log(response)
				$scope.interfList = response.data.data;
				deferred.resolve(response.data.data)
				$scope.node[id].ifList = response.data.data;
				$scope.interfListCount=false;
			}, function errorCallback(response){
				$scope.interfListCount=false;
				deferred.reject('Error')
				console.log('Server Error');
				console.log(response.data);
			}
		);
		return deferred.promise;
	}
	
	$scope.setPosition = function(top,left,id,type){
		$http({
			method: 'PUT',
			url:'/api/labs'+$rootScope.lab+'/'+type+'s/'+id,
			data: {'left':left, 'top':top }}).then(
			function successCallback(response){
				//console.log(response)
				console.log('Position of '+type+' with id '+id+' saved')
				//$scope.interfList = response.data.data;
			}, function errorCallback(response){
				console.log('Server Error');
				console.log(response);
			}
		);
	}
	///////////////////////////////
	//More controllers //START
	ModalCtrl($scope, $uibModal, $log)
	//sidebarCtrl($scope)
	//More controllers //END
	//Escape from all//START
	function escapefunction() {
		window.onkeyup = function(e) {
			if ( e.keyCode === 27 ) {
				console.log('Esc')
				$('body').removeClass('sidebar-expanded-on-hover').addClass('sidebar-collapse');
				$scope.changedCursorNode=false;
				$scope.changedCursorNet=false;
				$('#mainDIV').removeClass("router-cursor").removeClass("network-cursor");
				$('.treeview').addClass("openright");
			}
		}
	}
	escapefunction();
	//Escape from all//END
}

function ObjectLength( object ) {
    var length = 0;
    for( var key in object ) {
        if( object.hasOwnProperty(key) ) {
            ++length;
        }
    }
	return length;
}
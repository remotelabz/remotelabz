function usermgmtController($scope, $http, $rootScope, $uibModal, $log) {
	$scope.testAUTH("/usermgmt"); //TEST AUTH
	$scope.userdata='';
	////Invisible columns//START
	$scope.sessionTime=false;
	$scope.sessionIP=false;
	$scope.currentFolder=false;
	$scope.currentLab=false;
	$scope.edituser='';
	////Invisible columns//END
	$('body').removeClass().addClass('hold-transition skin-blue layout-top-nav');
	//Get users //START
	$scope.getUsersInfo = function(){
	$http.get('/api/users/').then(
			function successCallback(response) {
				//console.log(response.data.data);
				$scope.userdata=response.data.data;
				$.unblockUI();
			}, 
			function errorCallback(response) {
				$.unblockUI();
				console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");}	
	);
	}
	$scope.getUsersInfo()
	//Get users //END
	/////////////////
	//Delete user //START
	$scope.deleteUser = function(username){
		console.log('hrer')
		if (confirm('Are you sure you want to delete user '+username+'?')) {
			$http({
				method: 'DELETE',
				url: '/api/users/'+username})
			.then(
				function successCallback(response) {
					//console.log(response)
					$scope.getUsersInfo()
				}, 
				function errorCallback(response) {
					console.log(response)
					console.log("Unknown Error. Why did API doesn't respond?")
					$location.path("/login");
				}
			);
		} else return;
	}
	//Delete user //END
	//////////////////
	//Time converter //START
	$scope.timeConverter = function(UNIX_timestamp){
		var a = new Date(UNIX_timestamp * 1000);
		var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
		var year = a.getFullYear();
		var month = months[a.getMonth()];
		var date = a.getDate();
		var hour = a.getHours();
		var min = a.getMinutes();
		var sec = a.getSeconds();
		var time = date + ' ' + month + ' ' + year + ' ' + hour + ':' + min + ':' + sec ;
		return time;
	}
	//Time converter //END
	///////////////////////
	//More controllers //START
	ModalCtrl($scope, $uibModal, $log)
	//More controllers //END
}
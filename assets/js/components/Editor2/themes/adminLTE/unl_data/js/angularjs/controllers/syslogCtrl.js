function syslogController($scope, $http, $rootScope) {
	$scope.testAUTH("/syslog"); //TEST AUTH
	$('body').removeClass().addClass('hold-transition skin-blue layout-top-nav');
	$scope.fileselect=false;
	$scope.lineCount=20;
	$scope.searchText='';
	$scope.logInfo=[];
	$scope.accessLog=[];
	$scope.apiLog=[];
	$scope.errorLog=[];
	$scope.php_errorsLog=[];
	$scope.unl_wrapperLog=[];
	$scope.blockButtons=false;
	$scope.blockButtonsClass='';
	$scope.logfiles= ['access.txt', 'api.txt','error.txt','php_errors.txt','unl_wrapper.txt','cpulimit.log']
	
	$scope.readFile = function(filename){
		//console.log(filename)
		filename = (filename === undefined) ? $scope.fileSelection : filename
		$scope.blockButtons=true;
		$scope.blockButtonsClass='m-progress';
		$scope.logInfo=[];
		$http.get('/api/logs/'+filename+'/'+$scope.lineCount+'/'+$scope.searchText).then(
			function successCallback(response) {
				//console.log(response.data)
				$scope.fileselect=true;	
				$scope.logInfo=response.data;
				$.unblockUI();
				$scope.blockButtons=false;
				$scope.blockButtonsClass='';
			}, 
			function errorCallback(response) {
				console.log(response)
				console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");
				$.unblockUI();
				$scope.blockButtons=false;
				$scope.blockButtonsClass='';
				}	
		);
	}
	$scope.readFile('access.txt')
}

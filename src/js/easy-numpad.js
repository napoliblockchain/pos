function show_easy_numpad() {
    var easy_numpad = `
        <div class="easy-numpad-frame" id="easy-numpad-frame">
            <div class="easy-numpad-container">
              <div class="easy-numpad-frame-output-container">
                <center>
                <div class="easy-numpad-output-container">
                  <div class="card-header alert error-header" role="alert" style="display:none;">
                    <p class="error-message"></p>
                  </div>
                  <div class='easy-numpad-output-container-content'>
                    <small class="float-right easy-numpad-output-symbol">€</small>
                    <p class="easy-numpad-output" id="easy-numpad-output">0</p>
                    <small class="card-title float-right"><i class="fab fa-btc easy-numpad-output-bitcoin-symbol"></i></small>
                    <p class="easy-numpad-output-bitcoin" id="easy-numpad-output-bitcoin">0</p>
                  </div>
                </div>
                </center>
              </div>

                <div class="easy-numpad-number-container">
                    <table border=0>
                        <tr>
                            <td width="33%"><span onclick="easynum(1)">1</span></td>
                            <td width="33%"><span onclick="easynum(2)">2</span></td>
                            <td width="33%"><span onclick="easynum(3)">3</span></td>
                        </tr>
                        <tr>
                            <td><span onclick="easynum(4)">4</span></td>
                            <td><span onclick="easynum(5)">5</span></td>
                            <td><span onclick="easynum(6)">6</span></td>

                        </tr>
                        <tr>
                            <td><span onclick="easynum(7)">7</a></td>
                            <td><span onclick="easynum(8)">8</a></td>
                            <td><span onclick="easynum(9)">9</a></td>
                        </tr>
                        <tr>
                            <td><span onclick="easynum(\'.\')">.</span></td>
                            <td><span onclick="easynum(0)">0</span></td>
                            <td><span class="del" id="del" onclick="easy_numpad_del()"><i class="fa fa-undo"></i></span></td>
                        </tr>
                    </table>
                    <center>
                      <small>
                        <button class="btn btn-success btn-sm done" id="done" name='confirm' type='button'>
                          <i class="fab fa-btc smaller"></i>&nbsp;<span id='waiting_span-btc' class="smaller">bitcoin</span>
                        </button>
                      </small>

                      <small>
                        <button class="btn btn-primary btn-sm token" id="token" name='token' type='button'>
                          <i class="fab fa-ethereum smaller"></i>&nbsp;<span id='waiting_span-token' class="smaller">token&nbsp;&nbsp;</span>
                        </button>
                      </small>
                    </center>
                </div>
            </div>
        </div>
    `;
    $('.card-body').append(easy_numpad);
}

function easy_numpad_close() {
    $('#easy-numpad-frame').remove();
}

function update_bitcoin(){
	var quote = $('#rtp_price').text();
	if (isNaN(quote))
			return true;

	var base = $('#easy-numpad-output').text();

	var result = ebc_arrotonda( base / quote , 8);
	$('#easy-numpad-output-bitcoin').text( result );

}

function easynum(num) {
    event.stopPropagation();

    navigator.vibrate = navigator.vibrate || navigator.webkitVibrate || navigator.mozVibrate || navigator.msVibrate;
    if (navigator.vibrate) {
        navigator.vibrate(60);
    }

    var easy_num_text = $('#easy-numpad-output').text();

    // console.log('Text length: ',easy_num_text.length());

    if (isNaN(num)){
        if(!easy_num_text.includes('.'))
            //$('#easy-numpad-output').text(easy_num_text+num);
            $('#easy-numpad-output').append(num);
    }else{
        if (eval(easy_num_text) == 0){
            if (easy_num_text.includes('.'))
                $('#easy-numpad-output').append(num);
            else if (num != 0)
                $('#easy-numpad-output').text(num);
        }
        else
            $('#easy-numpad-output').append(num);
    }


	update_bitcoin();

}
function easy_numpad_del() {
    event.preventDefault();
    var easy_numpad_output_val = $('#easy-numpad-output').text();
    var easy_numpad_output_val_deleted = easy_numpad_output_val.slice(0, -1);
    if (easy_numpad_output_val_deleted == '')
        easy_numpad_output_val_deleted = 0;
    $('#easy-numpad-output').text(easy_numpad_output_val_deleted);
    $('.error-header').removeClass("bg-danger");
    $('.error-message').html("&zwnj;"); //blank javascript char

	update_bitcoin();
}
// function easy_numpad_clear() {
//     event.preventDefault();
//     $('#easy-numpad-output').text("");
// 	update_bitcoin();
// }
//function easy_numpad_cancel() {
//    event.preventDefault();
//    $('#easy-numpad-frame').remove();
//}
function ebc_arrotonda(numero,x) {
	var number = Math.round(numero*Math.pow(10,x))/Math.pow(10,x);
	return Number(number.toString().substring(0,11));
}

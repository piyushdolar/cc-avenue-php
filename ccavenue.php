<?php 
  class CCAvenue(){
  
    private function encrypt($plainText,$key){
      $key = $this->hextobin(md5($key));
      $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
      $openMode = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
      $encryptedText = bin2hex($openMode);
      return $encryptedText;
    }
    
    private function decrypt($encryptedText,$key){
      $key = $this->hextobin(md5($key));
      $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
      $encryptedText = $this->hextobin($encryptedText);
      $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
      return $decryptedText;
    }
    
    private function hextobin($hexString){
      $length = strlen($hexString);
      $binString="";
      $count=0;
      while($count<$length){
          $subString =substr($hexString,$count,2);
          $packedString = pack("H*",$subString);
          if ($count==0){ $binString=$packedString; }else{ $binString.=$packedString; }
          $count+=2;
      }
      return $binString;
    }
    
    private function trans_decrypt($param){
      $encResponse	=	$this->input->post("encResp");			//This is the response sent by the CCAvenue Server
      $rcvdString		=	$this->decrypt($encResponse,$this->bankDetail['key']);		//Crypto Decryption used as per the specified working key.
      $decryptValues	=	explode('&', $rcvdString);
      $dataSize	=	sizeof($decryptValues);

      $collect = array();
      for($i = 0; $i < $dataSize; $i++){
        $v	=	explode('=',$decryptValues[$i]);
        $collect[$v[0]] = $v[1];
      }
      return $collect;
    }
    
    //FINAL STEP TO DECRYPT DATA
    public function transaction($type){
      // SUCCESS
      if($type=='status'){
        $data['v'] = $this->trans_decrypt($this->input->post());
        // On Success
        if($data['v']['order_status'] == 'Success'){
          $data['product_info'] = $this->db->get_where('tbl_products',array('product_id'=>$this->session->userdata('temp_order')))->row();
          $data['waybill'] = $this->confirmOrder($data['v']);
          $this->load->template('product/transaction',$data);

        // On Failure
        }else if($data['v']['order_status'] == 'Failure'){
          $this->session->set_flashdata('error','Transactino time out!');
          redirect('index.php/Welcome');
        }
      // CANCEL
      }else if($type=='cancel'){
        $data['v'] = $this->trans_decrypt($this->input->post());
        $data['type'] = 'success';
        $this->load->template('product/tran_cancel',$data);
      }
    }
    
    //CHECKOUT FIRST STEP START FROM HERE
    public function checkout(){
      $Formdata = $this->input->post('formData');
      $product_id = $Formdata[10]['value'];
      $last_row = $this->db->select("MAX(o_id) as last_id")->from('tbl_orders')->get()->row()->last_id;
      $last_row += 1;			
      $merchant_data='';
      foreach ($Formdata as $key => $value){
        $merchant_data	.=	$Formdata[$key]['name'].'='.urlencode($Formdata[$key]['value']).'&';
      }
      // Update Form
      $merchant_data	.= 'delivery_name'.'='.$Formdata[0]['value'].' '.$Formdata[1]['value'].'&';
      $merchant_data	.= 'delivery_country'.'= India&';
      $merchant_data	.= 'currency'.'='.'INR&';
      $merchant_data 	.= 'merchant_id'.'='.$this->bankDetail['id'].'&';
      $merchant_data 	.= 'order_id'.'='.$last_row.'&';
      $merchant_data 	.= 'offer_code'.'='.PROMO_CODE.'&';
      $merchant_data 	.= 'redirect_url'.'='.url('index.php/Welcome/transaction/status').'&';
      $merchant_data 	.= 'cancel_url'.'='.url('index.php/Welcome/transaction/cancel').'&';
      // Encrypt
      $encrypted_data	=	$this->encrypt($merchant_data,$this->bankDetail['key']);
      $data['key'] = $this->bankDetail['code'];
      $data['data'] = $encrypted_data;			
      echo json_encode($data);
    }
  }
?>

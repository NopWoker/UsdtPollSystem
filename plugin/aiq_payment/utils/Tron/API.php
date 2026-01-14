<?php

declare(strict_types = 1);

namespace Plugin\aiq_payment\Utils\Tron;

use Plugin\aiq_payment\Utils\Tron\Crypto\Keccak;
use Plugin\aiq_payment\Utils\Tron\Crypto\Secp;
use Plugin\aiq_payment\Utils\Tron\Requests;
use Plugin\aiq_payment\Utils\Tron\Transactions;
use Elliptic\EC;
use InvalidArgumentException;
use Exception;

final class API extends Tools {
	protected Requests $sender;
	private string $privatekey;
	private string $wallet;
	private ?string $mnemonic = null;

	public function __construct(string $apiurl = 'https://api.trongrid.io',? string $privatekey = null,? string $wallet = null){
		$this->sender = new Requests($apiurl);
		if(is_null($privatekey) === false) $this->privatekey = $privatekey;
		if(is_null($wallet) === false) $this->wallet = $this->hex2address($wallet);
	}
	public function setPrivateKey(string $privatekey) : void {
		$this->privatekey = $privatekey;
	}
	public function setWallet(string $wallet) : void {
		$this->wallet = $this->hex2address($wallet);
	}
	public function createTransaction(string $to,float $amount,? string $from = null,? string $extradata = null,bool $sun = false) : object {
		$to = $this->address2hex($to);
		if(is_null($from) && isset($this->wallet) === false) throw new InvalidArgumentException('The from argument is empty and no wallet is set by default !');
		$from = $this->address2hex(is_null($from) ? $this->wallet : $from);
		if($from === $to) throw new InvalidArgumentException('The from and to arguments cannot be the same !');
		$data = [
			'owner_address'=>$from,
			'to_address'=>$to,
			'amount'=>($sun ? $amount : $amount * 1e6)
		];
		if(is_null($extradata) === false) $data['extra_data'] = bin2hex($extradata);
		$transaction = (array) $this->sender->request('POST','wallet/createtransaction',$data);
		$signature = $this->signature($transaction);
		if(is_null($extradata) === false) $signature['raw_data']->data = bin2hex($extradata);
		$broadcast = (array) $this->broadcast($signature);
		return (object) array_merge($broadcast,$signature);
	}
	public function transferAsset(string $to,string $tokenid,float $amount,? string $from = null,? string $extradata = null,bool $sun = false) : object {
		$to = $this->address2hex($to);
		if(is_null($from) && isset($this->wallet) === false) throw new InvalidArgumentException('The from argument is empty and no wallet is set by default !');
		$from = $this->address2hex(is_null($from) ? $this->wallet : $from);
		if($from === $to) throw new InvalidArgumentException('The from and to arguments cannot be the same !');
		$data = [
			'owner_address'=>$from,
			'to_address'=>$to,
			'asset_name'=>bin2hex($tokenid),
			'amount'=>($sun ? $amount : $amount * 1e6)
		];
		if(is_null($extradata) === false) $data['extra_data'] = bin2hex($extradata);
		$transaction = (array) $this->sender->request('POST','wallet/transferasset',$data);
		$signature = $this->signature($transaction);
		if(is_null($extradata) === false) $signature['raw_data']->data = bin2hex($extradata);
		$broadcast = (array) $this->broadcast($signature);
		return (object) array_merge($broadcast,$signature);
	}
	public function generateAddress() : object {
		$ec = new EC('secp256k1');

		// 检查是否已有私钥
		if(isset($this->privatekey) && !empty($this->privatekey)) {
			// 从已有私钥生成公钥
			try {
				$privKey = $this->privatekey;
				$key = $ec->keyFromPrivate($privKey);
				$pubKey = $key->getPublic(false, 'hex');
			} catch (\Exception $e) {
				throw new \Exception('Failed to generate public key from private key: ' . $e->getMessage());
			}
		} else {
			// 生成新的助记词和密钥对 (BIP39 + BIP44)
			try {
				// 1. 生成 12 个助记词
				$mnemonic = $this->generateMnemonic();
				$this->mnemonic = $mnemonic;

				// 2. 助记词转种子
				$seed = $this->mnemonicToSeed($mnemonic);

				// 3. BIP44 派生 (m/44'/195'/0'/0/0)
				$privKey = $this->derivePrivateKey($seed);
				
				$key = $ec->keyFromPrivate($privKey);
				$pubKey = $key->getPublic(false, 'hex');
			} catch (\Exception $e) {
				throw new \Exception('Failed to generate HD wallet: ' . $e->getMessage());
			}
		}
		
		$address = $this->getAddressHexFromPublicKey($pubKey);
		$wallet = $this->hex2address($address);
		if(isset($this->privatekey) === false) $this->privatekey = $privKey;
		if(isset($this->wallet) === false) $this->wallet = $wallet;
		
		$result = [
			'privatekey' => $privKey,
			'publickey' => $pubKey,
			'address' => $address,
			'wallet' => $wallet
		];
		
		if (isset($this->mnemonic)) {
			$result['mnemonic'] = $this->mnemonic;
		}
		
		return (object) $result;
	}
	public function getAddressHexFromPublicKey(string $publickey) : string {
		$publickey = hex2bin($publickey);
		$publickey = substr($publickey,-64);
		$hash = Keccak::hash($publickey,256);
		return strval(41).substr($hash,24);
	}
	private function getWords() : array {
		$path = strval(__DIR__.DIRECTORY_SEPARATOR.'english.txt');
		if(file_exists($path) === false) throw new Exception('english.txt file doesn\'t exists !');
		$words = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		return array_map('trim', $words);
	}
	public function getPrivateKeyFromPhrase(string $phrase) : string {
		$seed = $this->mnemonicToSeed($phrase);
		$privKey = $this->derivePrivateKey($seed);
		return $privKey;
	}

	public function getPhraseFromPrivateKey(string $privatekey) : string {
		if (isset($this->privatekey) && $this->privatekey === $privatekey && isset($this->mnemonic)) {
			return $this->mnemonic;
		}
		throw new \Exception('Standard BIP39 mnemonics cannot be derived from a private key alone. Mnemonics are the source of the private key, not the other way around.');
	}

	/**
	 * BIP39 Mnemonic Generation
	 */
	private function generateMnemonic(int $strength = 128): string {
		if ($strength % 32 !== 0) {
			throw new \InvalidArgumentException("Invalid strength");
		}
		
		$entropy = openssl_random_pseudo_bytes($strength / 8);
		$hash = hash('sha256', $entropy);
		
		// Convert entropy to binary string
		$entropyBin = '';
		for ($i = 0; $i < strlen($entropy); $i++) {
			$entropyBin .= str_pad(decbin(ord($entropy[$i])), 8, '0', STR_PAD_LEFT);
		}
		
		// Convert hash to binary string
		$hashBin = '';
		for ($i = 0; $i < strlen($hash); $i++) {
			$hashBin .= str_pad(decbin(hexdec($hash[$i])), 4, '0', STR_PAD_LEFT);
		}
		
		// Checksum is first ENT / 32 bits of hash
		$checksumLen = $strength / 32;
		$checksumBin = substr($hashBin, 0, $checksumLen);
		
		$bits = $entropyBin . $checksumBin;
		$words = $this->getWords();
		$mnemonic = [];
		
		$chunks = str_split($bits, 11);
		foreach ($chunks as $chunk) {
			$index = bindec($chunk);
			$mnemonic[] = $words[$index];
		}
		
		return implode(' ', $mnemonic);
	}

	/**
	 * BIP39 Mnemonic to Seed
	 */
	private function mnemonicToSeed(string $mnemonic, string $passphrase = ''): string {
		$mnemonic = trim(preg_replace('/\s+/', ' ', $mnemonic));
		return hash_pbkdf2('sha512', $mnemonic, 'mnemonic' . $passphrase, 2048, 64, true); // Raw binary output
	}

	/**
	 * BIP32/BIP44 Key Derivation
	 * Path: m/44'/195'/0'/0/0
	 */
	private function derivePrivateKey(string $seed): string {
		// Master Key
		$I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
		$masterPriv = substr($I, 0, 32);
		$masterChain = substr($I, 32, 32);
		
		// m/44'
		list($priv1, $chain1) = $this->ckdPriv($masterPriv, $masterChain, 44 | 0x80000000);
		
		// m/44'/195' (Tron)
		list($priv2, $chain2) = $this->ckdPriv($priv1, $chain1, 195 | 0x80000000);
		
		// m/44'/195'/0' (Default Account)
		list($priv3, $chain3) = $this->ckdPriv($priv2, $chain2, 0 | 0x80000000);
		
		// m/44'/195'/0'/0 (External Chain)
		list($priv4, $chain4) = $this->ckdPriv($priv3, $chain3, 0);
		
		// m/44'/195'/0'/0/0 (First Address)
		list($priv5, $chain5) = $this->ckdPriv($priv4, $chain4, 0);
		
		return bin2hex($priv5);
	}

	/**
	 * Child Key Derivation (Private)
	 */
	private function ckdPriv(string $parentPriv, string $parentChain, int $index): array {
		$isHardened = ($index & 0x80000000) !== 0;
		
		$data = '';
		if ($isHardened) {
			$data = "\x00" . $parentPriv . pack('N', $index);
		} else {
			// Need parent public key
			$ec = new EC('secp256k1');
			$key = $ec->keyFromPrivate(bin2hex($parentPriv));
			// Compressed public key required for BIP32
			$parentPub = hex2bin($key->getPublic(true, 'hex'));
			$data = $parentPub . pack('N', $index);
		}
		
		$I = hash_hmac('sha512', $data, $parentChain, true);
		$IL = substr($I, 0, 32);
		$IR = substr($I, 32, 32);
		
		// childPriv = (IL + parentPriv) % n
		// Use BigInteger for modular addition
		$n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16); // secp256k1 order
		
		$ilNum = gmp_import($IL);
		$parentPrivNum = gmp_import($parentPriv);
		
		$childPrivNum = gmp_mod(gmp_add($ilNum, $parentPrivNum), $n);
		
		// Check for invalid keys (extremely rare)
		if (gmp_cmp($ilNum, $n) >= 0 || gmp_cmp($childPrivNum, 0) == 0) {
			// In strict BIP32, we should recurse with index+1, but simplified here
			throw new \Exception('Invalid key derivation');
		}
		
		$childPriv = gmp_export($childPrivNum);
		$childPriv = str_pad($childPriv, 32, "\x00", STR_PAD_LEFT);
		
		return [$childPriv, $IR];
	}
	public function getTransactionById(string $txID,bool $visible = true) : object {
		$data = [
			'value'=>$txID,
			'visible'=>$visible
		];
		$transaction = $this->sender->request('POST','wallet/gettransactionbyid',$data);
		return $transaction;
	}
	public function getTransactionInfoById(string $txID) : object {
		$data = [
			'value'=>$txID
		];
		$transaction = $this->sender->request('POST','wallet/gettransactioninfobyid',$data);
		return $transaction;
	}
	public function getTransactionInfoByBlockNum(int $num) : object {
		$data = [
			'num'=>$num
		];
		$transaction = $this->sender->request('POST','wallet/gettransactioninfobyblocknum',$data);
		return $transaction;
	}
	public function getTransactionsRelated(? string $address = null,? bool $confirmed = null,bool $to = false,bool $from = false,bool $searchinternal = true,int $limit = 20,string $order = 'block_timestamp,desc',? int $mintimestamp = null,? int $maxtimestamp = null) : object {
		$data = array();
		if(is_null($confirmed) === false) {
			$data[$confirmed ? 'only_confirmed' : 'only_unconfirmed'] = true;
		}
		$data['only_to'] = $to;
		$data['only_from'] = $from;
		$data['search_internal'] = $searchinternal;
		$data['limit'] = max(min($limit,200),20);
		$data['order_by'] = $order;
		if(is_null($mintimestamp) === false) {
			$data['min_timestamp'] = date('Y-m-d\TH:i:s.v\Z',$mintimestamp);
		}
		if(isset($maxtimestamp)) {
			$data['max_timestamp'] = date('Y-m-d\TH:i:s.v\Z',$maxtimestamp);
		}
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$address = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$transactions = $this->sender->request('GET','v1/accounts/'.$address.'/transactions',$data);
		if(isset($transactions->success) && $transactions->success === true) {
			$transactions->iterator = new Transactions($this->sender,array($transactions));
		}
		$this->sender = clone $this->sender;
		return $transactions;
	}
	public function getTransactionsFromAddress(? string $address = null,int $limit = 20) : object {
		return $this->getTransactionsRelated(address : $address,limit : $limit,from : true);
	}
	public function getTransactionsToAddress(? string $address = null,int $limit = 20) : object {
		return $this->getTransactionsRelated(address : $address,limit : $limit,to : true);
	}
	public function getTransactionsNext(string $url) : object {
		$transactions = $this->sender->request('GET',$url);
		if(isset($transactions->success) && $transactions->success === true) {
			$transactions->iterator = new Transactions($this->sender,array($transactions));
		}
		$this->sender = clone $this->sender;
		return $transactions;
	}
	public function createAccount(string $newaddress,? string $address = null) : object {
		$newaddress = $this->address2hex($newaddress);
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$address = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data = [
			'owner_address'=>$address,
			'account_address'=>$newaddress
		];
		$account = (array) $this->sender->request('POST','wallet/createaccount',$data);
		$signature = $this->signature($account);
		$broadcast = (array) $this->broadcast($signature);
		return (object) array_merge($broadcast,$signature);
	}
	public function getAccount(? string $address = null) : object {
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$address = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data = [
			'address'=>$address
		];
		$account = $this->sender->request('POST','walletsolidity/getaccount',$data);
		return $account;
	}
	public function getAccountNet(? string $address = null) : object {
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$address = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data = [
			'address'=>$address
		];
		$accountnet = $this->sender->request('POST','wallet/getaccountnet',$data);
		return $accountnet;
	}
	public function getAccountResource(? string $address = null) : object {
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$address = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data = [
			'address'=>$address
		];
		$accountresource = $this->sender->request('POST','wallet/getaccountresource',$data);
		return $accountresource;
	}
	public function getBalance(? string $address = null,bool $sun = false) : float {
		$account = $this->getAccount($address);
		$balance = isset($account->balance) ? $account->balance : 0;
		return ($sun ? $balance : $balance / 1e6);
	}
	public function getAccurateBalance(? string $address = null,bool $sun = false) : float {
		$account = $this->getAccount($address);
		$owner = isset($account->address) ? $account->address : $this->address2hex(is_null($address) ? $this->wallet : $address);
		$balance = isset($account->balance) ? $account->balance : 0;
		$unconfirmed = $this->getTransactionsRelated(address : $address,confirmed : false,limit : 200);
		if(isset($unconfirmed->iterator)) {
			foreach($unconfirmed->iterator as $transactions) {
				foreach($transactions->data as $transaction) {
					$contract = current($transaction->raw_data->contract)->parameter->value;
					$amount = $contract->amount;
					$from = $contract->owner_address;
					$ret = current($transaction->ret);
					$status = $ret->contractRet;
					$fee = $ret->fee;
					if($status === 'SUCCESS') {
						if($from === $owner) {
							$balance -= $amount + $fee;
						} else {
							$balance += $amount;
						}
					}
				}
			}
		}
		return ($sun ? $balance : $balance / 1e6);
	}
	public function getAccountName(? string $address = null,bool $hex = false) : mixed {
		$account = $this->getAccount($address);
		$accountname = isset($account->account_name) ? $account->account_name : null;
		return ($accountname ? ($hex ? $accountname : hex2bin($accountname)) : null);
	}
	public function freezeBalance(? string $address = null,int $balance = 0,int $duration = 3,string $resource = 'ENERGY',? string $receiver = null,bool $sun = false) : object {
		$data = array();
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$data['owner_address'] = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data['frozen_balance'] = ($sun ? $balance : $balance * 1e6);
		$data['frozen_duration'] = $duration;
		if(in_array($resource,['BANDWIDTH','ENERGY'])) {
			$data['resource'] = $resource;
		} else {
			throw new InvalidArgumentException('The resource argument must be ENERGY or BANDWIDTH');
		}
		if(is_null($receiver) === false) {
			$data['receiver_address'] = $this->address2hex($receiver);
		}
		$freezebalance = (array) $this->sender->request('POST','wallet/freezebalance',$data);
		$signature = $this->signature($freezebalance);
		$broadcast = (array) $this->broadcast($signature);
		return (object) array_merge($broadcast,$signature);
	}
	public function unfreezeBalance(? string $address = null,string $resource = 'ENERGY',? string $receiver = null) : object {
		$data = array();
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$data['owner_address'] = $this->address2hex(is_null($address) ? $this->wallet : $address);
		if(in_array($resource,['BANDWIDTH','ENERGY'])) {
			$data['resource'] = $resource;
		} else {
			throw new InvalidArgumentException('The resource argument must be ENERGY or BANDWIDTH');
		}
		if(is_null($receiver) === false) {
			$data['receiver_address'] = $this->address2hex($receiver);
		}
		$unfreezebalance = (array) $this->sender->request('POST','wallet/unfreezebalance',$data);
		$signature = $this->signature($unfreezebalance);
		$broadcast = (array) $this->broadcast($signature);
		return (object) array_merge($broadcast,$signature);
	}
	public function freezeBalanceV2(? string $address = null,int $balance = 0,string $resource = 'ENERGY',bool $sun = false) : object {
		$data = array();
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$data['owner_address'] = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data['frozen_balance'] = ($sun ? $balance : $balance * 1e6);
		if(in_array($resource,['BANDWIDTH','ENERGY'])) {
			$data['resource'] = $resource;
		} else {
			throw new InvalidArgumentException('The resource argument must be ENERGY or BANDWIDTH');
		}
		$freezebalance = (array) $this->sender->request('POST','wallet/freezebalancev2',$data);
		$signature = $this->signature($freezebalance);
		$broadcast = (array) $this->broadcast($signature);
		return (object) array_merge($broadcast,$signature);
	}
	public function unfreezeBalanceV2(? string $address = null,int $balance = 0,string $resource = 'ENERGY',bool $sun = false) : object {
		$data = array();
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$data['owner_address'] = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data['unfreeze_balance'] = ($sun ? $balance : $balance * 1e6);
		if(in_array($resource,['BANDWIDTH','ENERGY'])) {
			$data['resource'] = $resource;
		} else {
			throw new InvalidArgumentException('The resource argument must be ENERGY or BANDWIDTH');
		}
		$unfreezebalance = (array) $this->sender->request('POST','wallet/unfreezebalancev2',$data);
		$signature = $this->signature($unfreezebalance);
		$broadcast = (array) $this->broadcast($signature);
		return (object) array_merge($broadcast,$signature);
	}
	public function getDelegatedResource(string $to,? string $from = null) : object {
		$to = $this->address2hex($to);
		if(is_null($from) && isset($this->wallet) === false) throw new InvalidArgumentException('The from argument is empty and no wallet is set by default !');
		$from = $this->address2hex(is_null($from) ? $this->wallet : $from);
		$data = [
			'fromAddress'=>$from,
			'toAddress'=>$to
		];
		$delegatedresource = $this->sender->request('POST','wallet/getdelegatedresource',$data);
		return $delegatedresource;
	}
	public function getDelegatedResourceAccountIndex(? string $address = null) : object {
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$address = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data = [
			'value'=>$address
		];
		$delegatedresourceaccountindex = $this->sender->request('POST','wallet/getdelegatedresourceaccountindex',$data);
		return $delegatedresourceaccountindex;
	}
	public function changeAccountName(string $name,? string $address = null) : object {
		if(is_null($address) && isset($this->wallet) === false) throw new InvalidArgumentException('The address argument is empty and no wallet is set by default !');
		$address = $this->address2hex(is_null($address) ? $this->wallet : $address);
		$data = [
			'account_name'=>bin2hex($name),
			'owner_address'=>$address
		];
		$account = (array) $this->sender->request('POST','wallet/updateaccount',$data);
		$signature = $this->signature($account);
		$broadcast = $this->broadcast($signature);
		return $broadcast;
	}
	public function getBlock(string $id,bool $detail = false) : object {
		$data = [
			'id_or_num'=>$id,
			'detail'=>$detail
		];
		$block = $this->sender->request('POST','wallet/getblock',$data);
		return $block;
	}
	public function getBlockByNum(int $num) : object {
		$data = [
			'num'=>$num
		];
		$block = $this->sender->request('POST','wallet/getblockbynum',$data);
		return $block;
	}
	public function getBlockById(int $id) : object {
		$data = [
			'value'=>$id
		];
		$block = $this->sender->request('POST','wallet/getblockbyid',$data);
		return $block;
	}
	public function signature(array $response) : array {
		if(isset($this->privatekey)) {
			if(isset($response['Error'])) {
				throw new Exception($response['Error']);
			} else {
				if(isset($response['signature'])) {
					throw new Exception('response is already signed !');
				} elseif(isset($response['txID']) === false) {
					throw new Exception('The response does not have txID key !');
				} else {
					$signature = Secp::sign($response['txID'],$this->privatekey);
					$response['signature'] = array($signature);
				}
			}
		} else {
			throw new Exception('private key is not set');
		}
		return $response;
	}
	public function broadcast(array $response) : object {
		if(isset($response['signature']) === false || is_array($response['signature']) === false) throw new InvalidArgumentException('response has not been signature !');
		$broadcast = $this->sender->request('POST','wallet/broadcasttransaction',$response);
		return $broadcast;
	}
	public function __call(string $method,array $arguments) : mixed {
		return match($method){
			'sendTrx' , 'sendTron' , 'send' , 'sendTransaction' => $this->createTransaction(...$arguments),
			'sendToken' , 'sendTokenTransaction' => $this->transferAsset(...$arguments),
			'getBandwidth' => $this->getAccountNet(...$arguments),
			'registerAccount' => $this->createAccount(...$arguments),
			'createAddress' => $this->generateAddress(...$arguments),
			default => throw new Exception('Call to undefined method '.self::class.'::'.$method.'()')
		};
	}
}

?>
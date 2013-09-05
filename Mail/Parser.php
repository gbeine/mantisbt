<?php

require_once( 'Mail/mimeDecode.php' );

class Mail_Parser
{
    private $_parse_html = false;
    private $_parse_mime = false;
    private $_htmlparser = "/usr/bin/w3m -T text/html -dump";
    private $_htmltmpdir = "/tmp";

    private $_file;
    private $_content;
    
    private $_from;
    private $_subject;
    private $_transferencoding;
    private $_body;
    private $_parts = array ( );
    private $_ctype = array ( );

    function __construct( $options = array() ) {
	$this->_parse_mime = $options['parse_mime'];
	$this->_parse_html = $options['parse_html'];
	$this->_htmlparser = $options['htmlparser'];
	$this->_htmltmpdir = $options['htmltmpdir'];
    }
    
    public function setInputString ( $content ) {
        $this->_content = $content;
    }
    
    public function setInputFile( $file ) {
        $this->_file = $file;
        $this->_content = file_get_contents( $this->_file );
    }

    public function parse() {
        $decoder = new Mail_mimeDecode( $this->_content );
        $params['include_bodies'] = true;
        $params['decode_bodies'] = true;
        $params['decode_headers'] = true;
        $structure = $decoder->decode( $params );
	$this->parseStructure( $structure );
	unset( $this->_content );
    }
    
    public function from() {
	return $this->_from;
    }

    public function subject() {
	return $this->_subject;
    }

    public function priority() {
	return $this->_priority;
    }

    public function body() {
	return $this->_body;
    }

    public function parts() {
	return $this->_parts;
    }
    
    private function parseStructure( $structure ) {
	$this->setFrom( $structure->headers['from'] );
	$this->setSubject( $structure->headers['subject'] );
	$this->setContentType( $structure->ctype_primary, $structure->ctype_secondary );
	if ( isset( $structure->headers['x-priority'] ) ) {
	    $this->setPriority( $structure->headers['x-priority'] );
	}
	if ( isset( $structure->headers['content-transfer-encoding'] ) ) {
	    $this->setTransferEncoding( $structure->headers['content-transfer-encoding'] );
	}
	if ( isset( $structure->body ) ) {
	    $this->setBody( $structure->body );
	}
	if ( $this->_parse_mime && isset( $structure->parts ) ) {
	    $this->setParts( $structure->parts );
	}
    }

    private function setFrom( $from ) {
	$this->_from = quoted_printable_decode( $from );
    }

    private function setSubject( $subject ) {
	$this->_subject = quoted_printable_decode( $subject );
    }

    private function setPriority( $priority ) {
	$this->_priority = $priority;
    }

    private function setTransferEncoding( $transferencoding ) {
	$this->_transferencoding = $transferencoding;
    }
    
    private function setContentType( $primary, $secondary ) {
        $this->_ctype['primary'] = $primary;
	$this->_ctype['secondary'] = $secondary;
    }

    private function setBody( $body ) {
        if ( 0 == strlen( $body ) || 0 != strlen( $this->_body ) ) {
	    return;
	}
	if ( 'text' == $this->_ctype['primary'] &&
	     'plain' == $this->_ctype['secondary'] ) {
	    switch ( $this->_transferencoding ) {
	        case 'base64':
	        case '8bit':
	        case 'quoted-printable':
		    $this->_body = quoted_printable_decode( $body );
		    break;
		default:
		    $this->_body = $body;
		    break;
	    }
	} elseif ( $this->_parse_html &&
	           'text' == $this->_ctype['primary'] &&
	           'html' == $this->_ctype['secondary'] ) {
            $file = $this->_htmltmpdir . "/" . md5 ( $body );
            file_put_contents( $file, $body );
            $this->_body = shell_exec("cat " . $file . " | " . $this->_htmlparser);
            unlink($file);
	}
    }
    
    private function setParts( &$parts ) {
	$i = 0;
	if ( 'multipart' == $parts[$i]->ctype_primary ) {
	    $this->setParts( $parts[$i]->parts );
	    $i++;
	}
        if ( 'text' == $parts[$i]->ctype_primary &&
	    in_array( $parts[$i]->ctype_secondary, array( 'plain', 'html' ) ) ) {
	    $this->setContentType( $parts[$i]->ctype_primary, $parts[$i]->ctype_secondary );
	    $this->setTransferEncoding( $parts[$i]->headers['content-transfer-encoding'] );
	    $this->setBody( $parts[$i]->body );
	    $i++;
	}
        if ( isset( $parts[$i] ) &&
	    'text' == $parts[$i]->ctype_primary &&
	    'html' == $parts[$i]->ctype_secondary ) {
	    $i++;
	}
	for ( $i; $i < count( $parts ); $i++ ) {
	    $this->addPart( $parts[$i] );
	}
    }
    
    private function addPart( &$part ) {
	$p['ctype'] = $part->ctype_primary . "/" . $part->ctype_secondary;
	if ( isset( $part->ctype_parameters['name'] ) ) {
	    $p['name'] = $part->ctype_parameters['name'];
	}
	$p['body'] = $part->body;
	$this->_parts[] = $p;
    }
}

?>